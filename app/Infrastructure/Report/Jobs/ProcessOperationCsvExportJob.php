<?php

declare(strict_types=1);

namespace App\Infrastructure\Report\Jobs;

use App\Infrastructure\Report\OperationCsvReportGenerator;
use App\Models\OperationReportRun;
use App\Models\OperationReportRunChunk;
use App\Notifications\OperationReportFinishedNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Throwable;

final class ProcessOperationCsvExportJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 3600;

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
        $claimed = OperationReportRun::query()
            ->whereKey($this->operationReportRunId)
            ->where('status', OperationReportRun::STATUS_PENDING)
            ->update([
                'status' => OperationReportRun::STATUS_PROCESSING,
                'started_at' => now(),
                'failure_message' => null,
                'error_code' => null,
            ]);

        if ($claimed === 0) {
            return;
        }

        $operationReportRun = OperationReportRun::query()->findOrFail($this->operationReportRunId);

        try {
            /** @var array{status?: string, operation?: int, product?: string, agreement?: int} $filters */
            $filters = $operationReportRun->filters ?? [];
            $chunkPlan = $operationCsvReportGenerator->buildChunkPlan(
                filters: $filters,
                workerCount: $this->resolveParallelWorkers(),
            );
            $totalRows = $chunkPlan['total_rows'];

            $operationReportRun->forceFill([
                'total_rows' => $totalRows,
            ])->save();

            if ($totalRows === 0) {
                $summary = $operationCsvReportGenerator->mergeChunkFiles(
                    runId: $operationReportRun->id,
                    chunkRelativePaths: [],
                );

                $operationReportRun->forceFill([
                    'status' => OperationReportRun::STATUS_COMPLETED,
                    'output_file_path' => $summary['output_file_path'],
                    'total_rows' => 0,
                    'metrics' => [
                        'query' => 0.0,
                        'write' => 0.0,
                        'merge' => (float) ($summary['metrics']['merge'] ?? 0.0),
                        'total' => (float) ($summary['metrics']['total'] ?? 0.0),
                        'metadata' => [
                            'configured_workers' => $this->resolveParallelWorkers(),
                            'planned_chunks' => 0,
                            'completed_chunks' => 0,
                            'failed_chunks' => 0,
                        ],
                    ],
                    'finished_at' => now(),
                    'error_code' => null,
                ])->save();

                $this->notifyRequestedByUser($operationReportRun);

                return;
            }

            $chunks = $this->buildChunkPayloads(
                operationReportRunId: $operationReportRun->id,
                chunkPlanChunks: $chunkPlan['chunks'],
            );
            OperationReportRunChunk::query()->insert($chunks);

            $chunkIds = OperationReportRunChunk::query()
                ->where('operation_report_run_id', $operationReportRun->id)
                ->orderBy('chunk_index')
                ->pluck('id');

            foreach ($chunkIds as $chunkId) {
                dispatch(new ProcessOperationCsvExportChunkJob((int) $chunkId));
            }

            dispatch(new FinalizeOperationCsvExportRunJob($operationReportRun->id));
        } catch (Throwable $throwable) {
            $operationReportRun->forceFill([
                'status' => OperationReportRun::STATUS_FAILED,
                'failure_message' => $throwable->getMessage(),
                'error_code' => OperationReportRun::ERROR_CODE_UNEXPECTED,
                'metrics' => null,
                'finished_at' => now(),
            ])->save();

            $this->notifyRequestedByUser($operationReportRun);

            throw $throwable;
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->operationReportRunId;
    }

    public function failed(?Throwable $throwable): void
    {
        $operationReportRun = OperationReportRun::query()->find($this->operationReportRunId);

        if ($operationReportRun === null) {
            return;
        }

        if (
            $operationReportRun->status === OperationReportRun::STATUS_FAILED
            && $operationReportRun->finished_at !== null
        ) {
            return;
        }

        $operationReportRun->forceFill([
            'status' => OperationReportRun::STATUS_FAILED,
            'failure_message' => $throwable?->getMessage(),
            'error_code' => OperationReportRun::ERROR_CODE_UNEXPECTED,
            'finished_at' => now(),
        ])->save();

        $this->notifyRequestedByUser($operationReportRun);
    }

    private function notifyRequestedByUser(OperationReportRun $operationReportRun): void
    {
        $operationReportRun->requestedByUser?->notify(
            new OperationReportFinishedNotification($operationReportRun),
        );
    }

    /**
     * @param  list<array{chunk_index:int,start_operation_id:int,end_operation_id:int}>  $chunkPlanChunks
     * @return list<array{operation_report_run_id:int,chunk_index:int,start_operation_id:int,end_operation_id:int,status:string,output_file_path:null,total_rows:int,metrics:null,failure_message:null,started_at:null,finished_at:null,created_at:Carbon,updated_at:Carbon}>
     */
    private function buildChunkPayloads(int $operationReportRunId, array $chunkPlanChunks): array
    {
        $now = now();
        $payloads = [];

        foreach ($chunkPlanChunks as $chunk) {
            $payloads[] = [
                'operation_report_run_id' => $operationReportRunId,
                'chunk_index' => $chunk['chunk_index'],
                'start_operation_id' => $chunk['start_operation_id'],
                'end_operation_id' => $chunk['end_operation_id'],
                'status' => 'pending',
                'output_file_path' => null,
                'total_rows' => 0,
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

    private function resolveParallelWorkers(): int
    {
        return max(1, (int) config('imports.parallel_workers', 4));
    }
}
