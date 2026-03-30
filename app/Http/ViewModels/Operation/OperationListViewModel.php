<?php

declare(strict_types=1);

namespace App\Http\ViewModels\Operation;

use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\OperationStatusTransitions;
use App\Domain\Operation\ProductType;
use App\Models\Agreement;
use App\Models\OperationImportRun;
use App\Models\OperationReportRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class OperationListViewModel
{
    /**
     * @param  array{status?: string, operation?: int, product?: string, agreement?: int, per_page?: int}  $filters
     * @return array{
     *     operations: LengthAwarePaginator<int, array<string, mixed>>,
     *     filters: array{status?: string, operation?: int, product?: string, agreement?: int, per_page?: int},
     *     statusOptions: array<string, string>,
     *     statusSelectabilityByCurrentStatus: array<string, array<string, bool>>,
     *     statusBlockedReasonsByCurrentStatus: array<string, array<string, string>>,
     *     productOptions: array<string, string>,
     *     agreementOptions: array<int, string>,
     *     recentImportRuns: list<array{id:int,status:string,status_label:string,total_rows:int,imported_rows:int,rejected_rows:int,elapsed_seconds:int|null,finished_at:string|null,failure_message:string|null,error_code:string|null}>,
     *     recentReportRuns: list<array{id:int,status:string,status_label:string,total_rows:int,elapsed_seconds:int|null,finished_at:string|null,download_url:string|null,failure_message:string|null,error_code:string|null}>
     * }
     */
    public function toArray(
        LengthAwarePaginator $operations,
        array $filters,
        ?Collection $importRuns = null,
        ?Collection $reportRuns = null,
    ): array {
        $statusOptions = collect(OperationStatus::cases())
            ->mapWithKeys(static fn (OperationStatus $status): array => [$status->value => $status->label()])
            ->all();

        $statusSelectabilityByCurrentStatus = collect(OperationStatus::cases())
            ->mapWithKeys(static fn (OperationStatus $currentStatus): array => [
                $currentStatus->value => collect(OperationStatus::cases())
                    ->mapWithKeys(static fn (OperationStatus $targetStatus): array => [
                        $targetStatus->value => OperationStatusTransitions::canTransition($currentStatus, $targetStatus),
                    ])
                    ->all(),
            ])
            ->all();

        $statusBlockedReasonsByCurrentStatus = collect(OperationStatus::cases())
            ->mapWithKeys(static fn (OperationStatus $currentStatus): array => [
                $currentStatus->value => OperationStatusTransitions::blockedReasonsFrom($currentStatus),
            ])
            ->all();

        /** @var Paginator<int, array<string, mixed>> $formattedOperations */
        $formattedOperations = $operations->through(
            function (array $operation) use ($statusOptions, $statusSelectabilityByCurrentStatus, $statusBlockedReasonsByCurrentStatus): array {
                $currentStatus = (string) $operation['status']['value'];

                $quickStatusOptions = collect($statusOptions)
                    ->map(
                        function (string $label, string $value) use ($currentStatus, $statusSelectabilityByCurrentStatus, $statusBlockedReasonsByCurrentStatus): array {
                            $isSelectable = $statusSelectabilityByCurrentStatus[$currentStatus][$value] ?? false;

                            return [
                                'value' => $value,
                                'label' => $label,
                                'is_current' => $currentStatus === $value,
                                'is_selectable' => $isSelectable,
                                'blocked_reason' => $statusBlockedReasonsByCurrentStatus[$currentStatus][$value] ?? 'Sem permissao para transicao.',
                            ];
                        }
                    )
                    ->values()
                    ->all();

                $operation['operation_value_display'] = number_format((float) $operation['operation_value'], 2, ',', '.');
                $operation['quick_status_options'] = $quickStatusOptions;

                return $operation;
            }
        );

        return [
            'operations' => $formattedOperations,
            'filters' => $filters,
            'statusOptions' => $statusOptions,
            'statusSelectabilityByCurrentStatus' => $statusSelectabilityByCurrentStatus,
            'statusBlockedReasonsByCurrentStatus' => $statusBlockedReasonsByCurrentStatus,
            'productOptions' => collect(ProductType::cases())
                ->mapWithKeys(static fn (ProductType $productType): array => [$productType->value => $productType->label()])
                ->all(),
            'agreementOptions' => Agreement::query()
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
            'recentImportRuns' => $this->formatRecentImportRuns($importRuns),
            'recentReportRuns' => $this->formatRecentReportRuns($reportRuns),
        ];
    }

    /**
     * @return list<array{id:int,status:string,status_label:string,total_rows:int,imported_rows:int,rejected_rows:int,elapsed_seconds:int|null,finished_at:string|null,failure_message:string|null,error_code:string|null}>
     */
    public function formatRecentImportRuns(?Collection $importRuns): array
    {
        return $importRuns?->map(function (OperationImportRun $operationImportRun): array {
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
        })->values()->all() ?? [];
    }

    /**
     * @return list<array{id:int,status:string,status_label:string,total_rows:int,elapsed_seconds:int|null,finished_at:string|null,download_url:string|null,failure_message:string|null,error_code:string|null}>
     */
    public function formatRecentReportRuns(?Collection $reportRuns): array
    {
        return $reportRuns?->map(function (OperationReportRun $operationReportRun): array {
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
        })->values()->all() ?? [];
    }

    private function resolveElapsedSeconds(?Carbon $startedAt, ?Carbon $finishedAt): ?int
    {
        if ($startedAt === null || $finishedAt === null) {
            return null;
        }

        return (int) $startedAt->diffInSeconds($finishedAt);
    }
}
