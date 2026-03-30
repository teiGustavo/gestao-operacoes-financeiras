<?php

declare(strict_types=1);

namespace App\Infrastructure\Report\Jobs;

use App\Infrastructure\Report\OperationCsvReportGenerator;
use App\Models\OperationReportRun;
use App\Notifications\OperationReportFinishedNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
            ]);

        if ($claimed === 0) {
            return;
        }

        $operationReportRun = OperationReportRun::query()->findOrFail($this->operationReportRunId);

        try {
            /** @var array{status?: string, operation?: int, product?: string, agreement?: int} $filters */
            $filters = $operationReportRun->filters ?? [];
            $referenceDate = $operationReportRun->reference_date?->toDateTimeImmutable()
                ?? new \DateTimeImmutable('today');

            $summary = $operationCsvReportGenerator->generate(
                filters: $filters,
                referenceDate: $referenceDate,
                runId: $operationReportRun->id,
            );

            $operationReportRun->forceFill([
                'status' => OperationReportRun::STATUS_COMPLETED,
                'output_file_path' => $summary['output_file_path'],
                'total_rows' => $summary['total_rows'],
                'finished_at' => now(),
            ])->save();

            $this->notifyRequestedByUser($operationReportRun);
        } catch (Throwable $throwable) {
            $operationReportRun->forceFill([
                'status' => OperationReportRun::STATUS_FAILED,
                'failure_message' => $throwable->getMessage(),
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
}
