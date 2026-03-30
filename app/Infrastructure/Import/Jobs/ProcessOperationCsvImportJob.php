<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Jobs;

use App\Infrastructure\Import\OperationCsvImporter;
use App\Models\OperationImportRun;
use App\Models\OperationImportRunChunk;
use App\Notifications\OperationImportFinishedNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

final class ProcessOperationCsvImportJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    private const int MAX_PERSISTED_MESSAGE_LENGTH = 1_500;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $operationImportRunId)
    {
        $this->onQueue('imports');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [1, 5, 10];
    }

    public function handle(OperationCsvImporter $operationCsvImporter): void
    {
        $claimed = OperationImportRun::query()
            ->whereKey($this->operationImportRunId)
            ->where('status', OperationImportRun::STATUS_PENDING)
            ->update([
                'status' => OperationImportRun::STATUS_PROCESSING,
                'started_at' => now(),
                'failure_message' => null,
                'error_code' => null,
            ]);

        if ($claimed === 0) {
            return;
        }

        $operationImportRun = OperationImportRun::query()->findOrFail($this->operationImportRunId);

        try {
            $operationCsvImporter->ensureHeaderIsValid($operationImportRun->file_path);

            $chunkPlan = $operationCsvImporter->buildChunkPlan(
                filePath: $operationImportRun->file_path,
                workerCount: $this->resolveParallelWorkers(),
            );
            $totalRows = $chunkPlan['total_rows'];

            $operationImportRun->forceFill([
                'total_rows' => $totalRows,
            ])->save();

            if ($totalRows === 0) {
                $operationImportRun->forceFill([
                    'status' => OperationImportRun::STATUS_COMPLETED,
                    'imported_rows' => 0,
                    'rejected_rows' => 0,
                    'error_summary' => [],
                    'metrics' => ['total' => 0.0],
                    'finished_at' => now(),
                    'error_code' => null,
                ])->save();

                $this->notifyRequestedByUser($operationImportRun);

                return;
            }

            $chunks = $this->buildChunkPayloads(
                operationImportRunId: $operationImportRun->id,
                chunkPlanChunks: $chunkPlan['chunks'],
            );
            OperationImportRunChunk::query()->insert($chunks);

            $chunkIds = OperationImportRunChunk::query()
                ->where('operation_import_run_id', $operationImportRun->id)
                ->orderBy('chunk_index')
                ->pluck('id');

            foreach ($chunkIds as $chunkId) {
                dispatch(new ProcessOperationCsvImportChunkJob((int) $chunkId));
            }

            dispatch(new FinalizeOperationCsvImportRunJob($operationImportRun->id));
        } catch (Throwable $throwable) {
            $operationImportRun->forceFill([
                'status' => OperationImportRun::STATUS_FAILED,
                'failure_message' => $this->normalizeFailureMessage($throwable->getMessage()),
                'error_code' => OperationImportRun::ERROR_CODE_UNEXPECTED,
                'finished_at' => now(),
            ])->save();

            $this->notifyRequestedByUser($operationImportRun);

            throw $throwable;
        }
    }

    /**
     * @param  list<array{chunk_index:int,start_line_number:int,end_line_number:int,start_byte_offset:int}>  $chunkPlanChunks
     * @return list<array{operation_import_run_id:int,chunk_index:int,start_line_number:int,end_line_number:int,start_byte_offset:int,status:string,total_rows:int,imported_rows:int,rejected_rows:int,error_summary:null,metrics:null,failure_message:null,started_at:null,finished_at:null,created_at:Carbon,updated_at:Carbon}>
     */
    private function buildChunkPayloads(int $operationImportRunId, array $chunkPlanChunks): array
    {
        $now = now();
        $payloads = [];

        foreach ($chunkPlanChunks as $chunk) {
            $payloads[] = [
                'operation_import_run_id' => $operationImportRunId,
                'chunk_index' => $chunk['chunk_index'],
                'start_line_number' => $chunk['start_line_number'],
                'end_line_number' => $chunk['end_line_number'],
                'start_byte_offset' => $chunk['start_byte_offset'],
                'status' => OperationImportRunChunk::STATUS_PENDING,
                'total_rows' => 0,
                'imported_rows' => 0,
                'rejected_rows' => 0,
                'error_summary' => null,
                'metrics' => null,
                'failure_message' => null,
                'started_at' => null,
                'finished_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $payloads;
    }

    public function uniqueId(): string
    {
        return (string) $this->operationImportRunId;
    }

    public function failed(?Throwable $throwable): void
    {
        $operationImportRun = OperationImportRun::query()->find($this->operationImportRunId);

        if ($operationImportRun === null) {
            return;
        }

        if (
            $operationImportRun->status === OperationImportRun::STATUS_FAILED
            && $operationImportRun->finished_at !== null
        ) {
            return;
        }

        $operationImportRun->forceFill([
            'status' => OperationImportRun::STATUS_FAILED,
            'failure_message' => $this->normalizeFailureMessage($throwable?->getMessage()),
            'error_code' => OperationImportRun::ERROR_CODE_UNEXPECTED,
            'finished_at' => now(),
        ])->save();

        $this->notifyRequestedByUser($operationImportRun);
    }

    private function notifyRequestedByUser(OperationImportRun $operationImportRun): void
    {
        $operationImportRun->requestedByUser?->notify(
            new OperationImportFinishedNotification($operationImportRun),
        );
    }

    private function resolveParallelWorkers(): int
    {
        $workers = (int) config('imports.parallel_workers', 4);

        return max(1, $workers);
    }

    private function normalizeFailureMessage(?string $message): string
    {
        $normalized = trim((string) $message);

        if ($normalized === '') {
            return 'falha inesperada durante o processamento da importacao';
        }

        $normalized = preg_replace('/\s*\(Connection:\s.*$/', '', $normalized) ?? $normalized;

        if (mb_strlen($normalized) <= self::MAX_PERSISTED_MESSAGE_LENGTH) {
            return $normalized;
        }

        return Str::limit($normalized, self::MAX_PERSISTED_MESSAGE_LENGTH, '...[truncated]');
    }
}
