<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Jobs;

use App\Infrastructure\Import\OperationCsvImporter;
use App\Models\OperationImportRunChunk;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class ProcessOperationCsvImportChunkJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $operationImportRunChunkId)
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
        $claimed = OperationImportRunChunk::query()
            ->whereKey($this->operationImportRunChunkId)
            ->where('status', OperationImportRunChunk::STATUS_PENDING)
            ->update([
                'status' => OperationImportRunChunk::STATUS_PROCESSING,
                'started_at' => now(),
                'failure_message' => null,
            ]);

        if ($claimed === 0) {
            return;
        }

        $chunk = OperationImportRunChunk::query()
            ->with('run')
            ->findOrFail($this->operationImportRunChunkId);

        try {
            $summary = $operationCsvImporter->importWithSummary(
                filePath: $chunk->run->file_path,
                operationImportRunId: $chunk->operation_import_run_id,
                startLineNumber: $chunk->start_line_number,
                endLineNumber: $chunk->end_line_number,
                startByteOffset: $chunk->start_byte_offset,
                shouldValidateHeader: false,
            );

            $chunk->forceFill([
                'status' => $summary['rejected_rows'] > 0
                    ? OperationImportRunChunk::STATUS_COMPLETED_WITH_ERRORS
                    : OperationImportRunChunk::STATUS_COMPLETED,
                'total_rows' => $summary['total_rows'],
                'imported_rows' => $summary['imported_rows'],
                'rejected_rows' => $summary['rejected_rows'],
                'error_summary' => $summary['error_summary'],
                'metrics' => $summary['metrics'],
                'finished_at' => now(),
                'failure_message' => null,
            ])->save();
        } catch (Throwable $throwable) {
            $chunk->forceFill([
                'status' => OperationImportRunChunk::STATUS_FAILED,
                'failure_message' => $throwable->getMessage(),
                'finished_at' => now(),
            ])->save();

            throw $throwable;
        } finally {
            dispatch(new FinalizeOperationCsvImportRunJob($chunk->operation_import_run_id));
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->operationImportRunChunkId;
    }

    public function failed(?Throwable $throwable): void
    {
        $chunk = OperationImportRunChunk::query()->find($this->operationImportRunChunkId);

        if ($chunk === null) {
            return;
        }

        if ($chunk->status !== OperationImportRunChunk::STATUS_FAILED) {
            $chunk->forceFill([
                'status' => OperationImportRunChunk::STATUS_FAILED,
                'failure_message' => $throwable?->getMessage(),
                'finished_at' => now(),
            ])->save();
        }

        dispatch(new FinalizeOperationCsvImportRunJob($chunk->operation_import_run_id));
    }
}
