<?php

declare(strict_types=1);

namespace App\Infrastructure\Report\Jobs;

use App\Infrastructure\Report\OperationCsvReportGenerator;
use App\Models\OperationReportRunChunk;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class ProcessOperationCsvExportChunkJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $operationReportRunChunkId)
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
        $claimed = OperationReportRunChunk::query()
            ->whereKey($this->operationReportRunChunkId)
            ->where('status', 'pending')
            ->update([
                'status' => 'processing',
                'started_at' => now(),
                'failure_message' => null,
            ]);

        if ($claimed === 0) {
            return;
        }

        $chunk = OperationReportRunChunk::query()->with('run')->findOrFail($this->operationReportRunChunkId);

        try {
            /** @var array{status?: string, operation?: int, product?: string, agreement?: int} $filters */
            $filters = $chunk->run->filters ?? [];
            $referenceDate = $chunk->run->reference_date?->toDateTimeImmutable()
                ?? new \DateTimeImmutable('today');

            $summary = $operationCsvReportGenerator->generateChunk(
                filters: $filters,
                referenceDate: $referenceDate,
                runId: $chunk->operation_report_run_id,
                chunkIndex: $chunk->chunk_index,
                startOperationId: (int) $chunk->start_operation_id,
                endOperationId: (int) $chunk->end_operation_id,
            );

            $chunk->forceFill([
                'status' => 'completed',
                'output_file_path' => $summary['output_file_path'],
                'total_rows' => $summary['total_rows'],
                'metrics' => $summary['metrics'],
                'failure_message' => null,
                'finished_at' => now(),
            ])->save();
        } catch (Throwable $throwable) {
            $chunk->forceFill([
                'status' => 'failed',
                'failure_message' => $throwable->getMessage(),
                'finished_at' => now(),
            ])->save();

            throw $throwable;
        } finally {
            dispatch(new FinalizeOperationCsvExportRunJob($chunk->operation_report_run_id));
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->operationReportRunChunkId;
    }

    public function failed(?Throwable $throwable): void
    {
        $chunk = OperationReportRunChunk::query()->find($this->operationReportRunChunkId);

        if ($chunk === null) {
            return;
        }

        if ($chunk->status !== 'failed') {
            $chunk->forceFill([
                'status' => 'failed',
                'failure_message' => $throwable?->getMessage(),
                'finished_at' => now(),
            ])->save();
        }

        dispatch(new FinalizeOperationCsvExportRunJob($chunk->operation_report_run_id));
    }
}
