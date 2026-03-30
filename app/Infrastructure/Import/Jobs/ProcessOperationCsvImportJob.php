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
use Illuminate\Support\Collection;
use Throwable;

final class ProcessOperationCsvImportJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    private const int WORKER_LINES_CHUNK_SIZE = 10_000;

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

            $totalRows = $operationCsvImporter->countDataRows($operationImportRun->file_path);

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

            $chunks = $this->buildChunkPayloads($operationImportRun->id, $totalRows);
            OperationImportRunChunk::query()->insert($chunks->all());

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
                'failure_message' => $throwable->getMessage(),
                'error_code' => OperationImportRun::ERROR_CODE_UNEXPECTED,
                'finished_at' => now(),
            ])->save();

            $this->notifyRequestedByUser($operationImportRun);

            throw $throwable;
        }
    }

    /**
     * @return Collection<int, array{operation_import_run_id:int,chunk_index:int,start_line_number:int,end_line_number:int,status:string,total_rows:int,imported_rows:int,rejected_rows:int,error_summary:null,metrics:null,failure_message:null,started_at:null,finished_at:null,created_at:Carbon,updated_at:Carbon}>
     */
    private function buildChunkPayloads(int $operationImportRunId, int $totalRows): Collection
    {
        $now = now();
        $payloads = collect();
        $chunkIndex = 1;
        $dataRowStart = 1;

        while ($dataRowStart <= $totalRows) {
            $dataRowEnd = min($dataRowStart + self::WORKER_LINES_CHUNK_SIZE - 1, $totalRows);

            $payloads->push([
                'operation_import_run_id' => $operationImportRunId,
                'chunk_index' => $chunkIndex,
                // +1 because line 1 is the CSV header.
                'start_line_number' => $dataRowStart + 1,
                'end_line_number' => $dataRowEnd + 1,
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
            ]);

            $dataRowStart = $dataRowEnd + 1;
            $chunkIndex++;
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
            'failure_message' => $throwable?->getMessage(),
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
}
