<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Infrastructure\Queries\Operation\OperationImportRunLatestQuery;
use App\Infrastructure\Queries\Operation\OperationReportRunLatestQuery;
use App\Models\OperationImportRun;
use App\Models\OperationReportRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class OperationRunsStatusController extends Controller
{
    public function __construct(
        private readonly OperationImportRunLatestQuery $operationImportRunLatestQuery,
        private readonly OperationReportRunLatestQuery $operationReportRunLatestQuery,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $importRuns = $this->operationImportRunLatestQuery->latestForUser($userId);
        $reportRuns = $this->operationReportRunLatestQuery->latestForUser($userId);

        return response()->json([
            'data' => [
                'recent_import_runs' => $importRuns->map(function (OperationImportRun $operationImportRun): array {
                    return [
                        'id' => $operationImportRun->id,
                        'status' => $operationImportRun->status,
                        'status_label' => $operationImportRun->statusLabel(),
                        'total_rows' => (int) $operationImportRun->total_rows,
                        'imported_rows' => (int) $operationImportRun->imported_rows,
                        'rejected_rows' => (int) $operationImportRun->rejected_rows,
                        'elapsed_seconds' => $this->resolveElapsedSeconds(
                            startedAt: $operationImportRun->started_at,
                            finishedAt: $operationImportRun->finished_at,
                        ),
                        'finished_at' => $operationImportRun->finished_at?->format('d/m/Y H:i:s'),
                        'failure_message' => $operationImportRun->failure_message,
                        'error_code' => $operationImportRun->resolvedErrorCode(),
                    ];
                })->values()->all(),
                'recent_report_runs' => $reportRuns->map(function (OperationReportRun $operationReportRun): array {
                    return [
                        'id' => $operationReportRun->id,
                        'status' => $operationReportRun->status,
                        'status_label' => $operationReportRun->statusLabel(),
                        'total_rows' => (int) $operationReportRun->total_rows,
                        'elapsed_seconds' => $this->resolveElapsedSeconds(
                            startedAt: $operationReportRun->started_at,
                            finishedAt: $operationReportRun->finished_at,
                        ),
                        'finished_at' => $operationReportRun->finished_at?->format('d/m/Y H:i:s'),
                        'download_url' => $operationReportRun->status === OperationReportRun::STATUS_COMPLETED
                            ? route('operations.report.csv.download', ['operationReportRun' => $operationReportRun->id])
                            : null,
                        'failure_message' => $operationReportRun->failure_message,
                        'error_code' => $operationReportRun->resolvedErrorCode(),
                    ];
                })->values()->all(),
                'refreshed_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function resolveElapsedSeconds(?Carbon $startedAt, ?Carbon $finishedAt): ?int
    {
        if ($startedAt === null || $finishedAt === null) {
            return null;
        }

        return (int) $startedAt->diffInSeconds($finishedAt);
    }
}
