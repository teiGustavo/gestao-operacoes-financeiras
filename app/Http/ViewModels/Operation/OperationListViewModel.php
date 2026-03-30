<?php

declare(strict_types=1);

namespace App\Http\ViewModels\Operation;

use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\OperationStatusTransitions;
use App\Domain\Operation\ProductType;
use App\Models\Agreement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

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
     *     agreementOptions: array<int, string>
     * }
     */
    public function toArray(LengthAwarePaginator $operations, array $filters): array
    {
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
        ];
    }
}
