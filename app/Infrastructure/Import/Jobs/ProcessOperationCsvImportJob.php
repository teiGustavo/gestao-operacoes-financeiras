<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Jobs;

use App\Infrastructure\Import\OperationCsvImporter;
use App\Models\OperationImportRun;
use App\Notifications\OperationImportFinishedNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class ProcessOperationCsvImportJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

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
            ]);

        if ($claimed === 0) {
            return;
        }

        $operationImportRun = OperationImportRun::query()->findOrFail($this->operationImportRunId);

        try {
            $summary = $operationCsvImporter->importWithSummary(
                filePath: $operationImportRun->file_path,
                operationImportRunId: $operationImportRun->id,
            );

            $operationImportRun->forceFill([
                'status' => $summary['rejected_rows'] > 0
                    ? OperationImportRun::STATUS_COMPLETED_WITH_ERRORS
                    : OperationImportRun::STATUS_COMPLETED,
                'total_rows' => $summary['total_rows'],
                'imported_rows' => $summary['imported_rows'],
                'rejected_rows' => $summary['rejected_rows'],
                'error_summary' => $summary['error_summary'],
                'metrics' => $summary['metrics'],
                'finished_at' => now(),
            ])->save();

            $this->notifyRequestedByUser($operationImportRun);
        } catch (Throwable $throwable) {
            $operationImportRun->forceFill([
                'status' => OperationImportRun::STATUS_FAILED,
                'failure_message' => $throwable->getMessage(),
                'finished_at' => now(),
            ])->save();

            $this->notifyRequestedByUser($operationImportRun);

            throw $throwable;
        }
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
