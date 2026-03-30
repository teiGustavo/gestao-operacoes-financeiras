<?php

declare(strict_types=1);

namespace App\Infrastructure\Report\Jobs;

use App\Infrastructure\Report\OperationCsvReportGenerator;
use App\Models\OperationReportRun;
use App\Models\OperationReportRunChunk;
use App\Notifications\OperationReportFinishedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

final class FinalizeOperationCsvExportRunJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly int $operationReportRunId)
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

    public function handle(OperationCsvReportGenerator $operationCsvReportGenerator): void
    {
        DB::transaction(function () use ($operationCsvReportGenerator): void {
            $run = OperationReportRun::query()
                ->lockForUpdate()
                ->with('requestedByUser')
                ->find($this->operationReportRunId);

            if ($run === null || $run->status !== OperationReportRun::STATUS_PROCESSING || $run->finished_at !== null) {
                return;
            }

            $pendingOrProcessingChunks = OperationReportRunChunk::query()
                ->where('operation_report_run_id', $run->id)
                ->whereIn('status', [
                    'pending',
                    'processing',
                ])
                ->exists();

            if ($pendingOrProcessingChunks) {
                return;
            }

            $chunks = OperationReportRunChunk::query()
                ->where('operation_report_run_id', $run->id)
                ->orderBy('chunk_index')
                ->get();

            if ($chunks->isEmpty()) {
                return;
            }

            $hasFailedChunk = $chunks->contains(
                fn (OperationReportRunChunk $chunk): bool => $chunk->status === 'failed',
            );

            if ($hasFailedChunk) {
                $firstFailureMessage = $chunks
                    ->first(fn (OperationReportRunChunk $chunk): bool => $chunk->status === 'failed')
                    ?->failure_message;

                $run->forceFill([
                    'status' => OperationReportRun::STATUS_FAILED,
                    'failure_message' => $firstFailureMessage,
                    'error_code' => OperationReportRun::ERROR_CODE_UNEXPECTED,
                    'finished_at' => now(),
                ])->save();

                $this->notifyRequestedByUser($run);

                return;
            }

            $chunkRelativePaths = $chunks
                ->pluck('output_file_path')
                ->filter(static fn (?string $path): bool => is_string($path) && $path !== '')
                ->values()
                ->all();

            $metrics = [
                'query' => 0.0,
                'write' => 0.0,
                'merge' => 0.0,
                'total' => 0.0,
                'metadata' => [
                    'configured_workers' => max(1, (int) config('imports.parallel_workers', 4)),
                    'planned_chunks' => $chunks->count(),
                    'completed_chunks' => $chunks->where('status', 'completed')->count(),
                    'failed_chunks' => 0,
                ],
            ];

            foreach ($chunks as $chunk) {
                $chunkMetrics = $chunk->metrics ?? [];
                $metrics['query'] += (float) ($chunkMetrics['query'] ?? 0.0);
                $metrics['write'] += (float) ($chunkMetrics['write'] ?? 0.0);
                $metrics['total'] += (float) ($chunkMetrics['total'] ?? 0.0);
            }

            $mergeSummary = $operationCsvReportGenerator->mergeChunkFiles(
                runId: $run->id,
                chunkRelativePaths: $chunkRelativePaths,
            );

            $metrics['merge'] = (float) ($mergeSummary['metrics']['merge'] ?? 0.0);
            $metrics['total'] += (float) ($mergeSummary['metrics']['total'] ?? 0.0);

            $run->forceFill([
                'status' => OperationReportRun::STATUS_COMPLETED,
                'output_file_path' => $mergeSummary['output_file_path'],
                'total_rows' => $mergeSummary['total_rows'],
                'metrics' => $metrics,
                'error_code' => null,
                'failure_message' => null,
                'finished_at' => now(),
            ])->save();

            $this->notifyRequestedByUser($run);
        });
    }

    private function notifyRequestedByUser(OperationReportRun $operationReportRun): void
    {
        $operationReportRun->requestedByUser?->notify(
            new OperationReportFinishedNotification($operationReportRun),
        );
    }
}
