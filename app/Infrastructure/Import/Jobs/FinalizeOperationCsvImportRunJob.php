<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Jobs;

use App\Models\OperationImportRun;
use App\Models\OperationImportRunChunk;
use App\Notifications\OperationImportFinishedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

final class FinalizeOperationCsvImportRunJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

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

    public function handle(): void
    {
        DB::transaction(function (): void {
            $run = OperationImportRun::query()
                ->lockForUpdate()
                ->with('requestedByUser')
                ->find($this->operationImportRunId);

            if ($run === null || $run->status !== OperationImportRun::STATUS_PROCESSING || $run->finished_at !== null) {
                return;
            }

            $pendingOrProcessingChunks = OperationImportRunChunk::query()
                ->where('operation_import_run_id', $run->id)
                ->whereIn('status', [
                    OperationImportRunChunk::STATUS_PENDING,
                    OperationImportRunChunk::STATUS_PROCESSING,
                ])
                ->exists();

            if ($pendingOrProcessingChunks) {
                return;
            }

            $chunks = OperationImportRunChunk::query()
                ->where('operation_import_run_id', $run->id)
                ->get();

            if ($chunks->isEmpty()) {
                return;
            }

            $hasFailedChunk = $chunks->contains(
                fn (OperationImportRunChunk $chunk): bool => $chunk->status === OperationImportRunChunk::STATUS_FAILED,
            );

            if ($hasFailedChunk) {
                $firstFailureMessage = $chunks
                    ->first(fn (OperationImportRunChunk $chunk): bool => $chunk->status === OperationImportRunChunk::STATUS_FAILED)
                    ?->failure_message;

                $run->forceFill([
                    'status' => OperationImportRun::STATUS_FAILED,
                    'failure_message' => $firstFailureMessage,
                    'error_code' => OperationImportRun::ERROR_CODE_UNEXPECTED,
                    'finished_at' => now(),
                ])->save();

                $this->notifyRequestedByUser($run);

                return;
            }

            $rejectedRows = (int) $chunks->sum('rejected_rows');
            $importedRows = (int) $chunks->sum('imported_rows');
            $totalRows = (int) $chunks->sum('total_rows');

            $errorSummary = [];

            foreach ($chunks as $chunk) {
                $chunkErrorSummary = $chunk->error_summary ?? [];

                foreach ($chunkErrorSummary as $message => $occurrences) {
                    $errorSummary[$message] = ($errorSummary[$message] ?? 0) + (int) $occurrences;
                }
            }

            $persistBreakdown = [
                'upsert_clients' => 0.0,
                'upsert_agreements' => 0.0,
                'load_client_ids' => 0.0,
                'insert_operations' => 0.0,
                'insert_installments' => 0.0,
                'total' => 0.0,
            ];

            $metrics = [
                'extract' => 0.0,
                'validate_header' => 0.0,
                'validate_rows' => 0.0,
                'persist_rows' => 0.0,
                'total' => 0.0,
                'persist_breakdown' => $persistBreakdown,
            ];

            foreach ($chunks as $chunk) {
                $chunkMetrics = $chunk->metrics ?? [];
                $metrics['extract'] += (float) ($chunkMetrics['extract'] ?? 0.0);
                $metrics['validate_header'] += (float) ($chunkMetrics['validate_header'] ?? 0.0);
                $metrics['validate_rows'] += (float) ($chunkMetrics['validate_rows'] ?? 0.0);
                $metrics['persist_rows'] += (float) ($chunkMetrics['persist_rows'] ?? 0.0);
                $metrics['total'] += (float) ($chunkMetrics['total'] ?? 0.0);

                $chunkPersistBreakdown = $chunkMetrics['persist_breakdown'] ?? [];

                foreach (array_keys($persistBreakdown) as $key) {
                    $metrics['persist_breakdown'][$key] += (float) ($chunkPersistBreakdown[$key] ?? 0.0);
                }
            }

            $run->forceFill([
                'status' => $rejectedRows > 0
                    ? OperationImportRun::STATUS_COMPLETED_WITH_ERRORS
                    : OperationImportRun::STATUS_COMPLETED,
                'total_rows' => $totalRows,
                'imported_rows' => $importedRows,
                'rejected_rows' => $rejectedRows,
                'error_summary' => $errorSummary,
                'metrics' => $metrics,
                'failure_message' => null,
                'error_code' => null,
                'finished_at' => now(),
            ])->save();

            $this->notifyRequestedByUser($run);
        });
    }

    private function notifyRequestedByUser(OperationImportRun $operationImportRun): void
    {
        $operationImportRun->requestedByUser?->notify(
            new OperationImportFinishedNotification($operationImportRun),
        );
    }
}
