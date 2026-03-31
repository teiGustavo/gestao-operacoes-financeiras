<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Jobs;

use App\Infrastructure\Import\OperationCsvImporter;
use App\Models\OperationImportRunChunk;
use App\Models\OperationImportRunError;
use App\Models\OperationImportStagingRow;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

final class ProcessOperationCsvImportChunkJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    private const int MAX_PERSISTED_MESSAGE_LENGTH = 1_500;

    public int $tries = 8;

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
        return [1, 5, 10, 20, 40, 60, 90, 120];
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
            $failureMessage = $this->normalizeFailureMessage($throwable->getMessage());

            if ($this->isRetryableInfrastructureFailure($throwable)) {
                $chunk->forceFill([
                    'status' => OperationImportRunChunk::STATUS_PENDING,
                    'failure_message' => $failureMessage,
                    'finished_at' => null,
                ])->save();

                $this->recordRetryAttemptFailure($chunk, $failureMessage);

                throw $throwable;
            }

            $chunk->forceFill([
                'status' => OperationImportRunChunk::STATUS_FAILED,
                'failure_message' => $failureMessage,
                'finished_at' => now(),
            ])->save();

            $this->recordInfrastructureFailure($chunk, $failureMessage);

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
                'failure_message' => $this->normalizeFailureMessage($throwable?->getMessage()),
                'finished_at' => now(),
            ])->save();

            $this->recordInfrastructureFailure($chunk, $this->normalizeFailureMessage($throwable?->getMessage()));
        }

        dispatch(new FinalizeOperationCsvImportRunJob($chunk->operation_import_run_id));
    }

    private function recordInfrastructureFailure(OperationImportRunChunk $chunk, ?string $message): void
    {
        $failureMessage = $message ?? 'falha desconhecida ao processar chunk';

        OperationImportRunError::query()->create([
            'operation_import_run_id' => $chunk->operation_import_run_id,
            'line_number' => null,
            'message' => sprintf('chunk %d [%d-%d]: %s', $chunk->chunk_index, $chunk->start_line_number, $chunk->end_line_number, $failureMessage),
            'row_payload' => [
                'chunk_id' => $chunk->id,
                'chunk_index' => $chunk->chunk_index,
                'start_line_number' => $chunk->start_line_number,
                'end_line_number' => $chunk->end_line_number,
            ],
        ]);

        OperationImportStagingRow::query()
            ->where('operation_import_run_id', $chunk->operation_import_run_id)
            ->whereBetween('line_number', [$chunk->start_line_number, $chunk->end_line_number])
            ->whereIn('status', [
                OperationImportStagingRow::STATUS_PENDING,
                OperationImportStagingRow::STATUS_VALIDATED,
            ])
            ->update([
                'status' => OperationImportStagingRow::STATUS_FAILED,
                'error_message' => $failureMessage,
                'processed_at' => now(),
            ]);
    }

    private function recordRetryAttemptFailure(OperationImportRunChunk $chunk, string $failureMessage): void
    {
        OperationImportRunError::query()->create([
            'operation_import_run_id' => $chunk->operation_import_run_id,
            'line_number' => null,
            'message' => sprintf('chunk %d [%d-%d] retrying: %s', $chunk->chunk_index, $chunk->start_line_number, $chunk->end_line_number, $failureMessage),
            'row_payload' => [
                'chunk_id' => $chunk->id,
                'chunk_index' => $chunk->chunk_index,
                'start_line_number' => $chunk->start_line_number,
                'end_line_number' => $chunk->end_line_number,
                'attempt' => $this->attempts(),
                'will_retry' => true,
            ],
        ]);
    }

    private function isRetryableInfrastructureFailure(Throwable $throwable): bool
    {
        $current = $throwable;

        while ($current !== null) {
            if ($current instanceof QueryException) {
                $sqlState = (string) ($current->errorInfo[0] ?? '');
                $driverCode = (int) ($current->errorInfo[1] ?? 0);

                return in_array($sqlState, ['40001', 'HY000'], true)
                    && in_array($driverCode, [1205, 1213], true);
            }

            $current = $current->getPrevious();
        }

        $message = mb_strtolower($throwable->getMessage());

        return str_contains($message, 'sqlstate[40001]')
            && (str_contains($message, '1213') || str_contains($message, '1205'));
    }

    private function normalizeFailureMessage(?string $message): string
    {
        $normalized = trim((string) $message);

        if ($normalized === '') {
            return 'falha desconhecida ao processar chunk';
        }

        // Drop giant SQL payloads to keep audit rows lightweight and IDE-safe.
        $normalized = preg_replace('/\s*\(Connection:\s.*$/', '', $normalized) ?? $normalized;

        if (mb_strlen($normalized) <= self::MAX_PERSISTED_MESSAGE_LENGTH) {
            return $normalized;
        }

        return Str::limit($normalized, self::MAX_PERSISTED_MESSAGE_LENGTH, '...[truncated]');
    }
}
