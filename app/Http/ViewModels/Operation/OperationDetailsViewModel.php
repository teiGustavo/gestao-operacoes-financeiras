<?php

declare(strict_types=1);

namespace App\Http\ViewModels\Operation;

use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\OperationStatusTransitions;
use App\Domain\Operation\ProductType;
use Illuminate\Support\Carbon;

final class OperationDetailsViewModel
{
    /**
     * @param  array<string, mixed>  $operation
     * @return array{
     *     operation: array<string, mixed>,
     *     statusOptions: array<int, array{value: string, label: string, is_current: bool, is_selected: bool, is_selectable: bool, blocked_reason: ?string}>,
     *     blockedStatuses: array<int, array{label: string, reason: string}>,
     *     statusSelectability: array<string, bool>,
     *     statusBlockedReasons: array<string, string>,
     *     selectedStatus: string,
     *     page: array{title: string}
     * }
     */
    public function toArray(array $operation): array
    {
        $currentStatus = OperationStatus::from((string) $operation['status']['value']);
        $selectedStatus = (string) old('status', (string) $operation['status']['value']);
        $statusSelectability = collect(OperationStatus::cases())
            ->mapWithKeys(static fn (OperationStatus $targetStatus): array => [
                $targetStatus->value => OperationStatusTransitions::canTransition($currentStatus, $targetStatus),
            ])
            ->all();
        $statusBlockedReasons = OperationStatusTransitions::blockedReasonsFrom($currentStatus);

        $statusOptions = collect(OperationStatus::cases())
            ->map(function (OperationStatus $status) use ($operation, $selectedStatus, $statusSelectability, $statusBlockedReasons): array {
                $isSelectable = $statusSelectability[$status->value] ?? false;

                return [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'is_current' => (string) $operation['status']['value'] === $status->value,
                    'is_selected' => $selectedStatus === $status->value,
                    'is_selectable' => $isSelectable,
                    'blocked_reason' => $isSelectable ? null : ($statusBlockedReasons[$status->value] ?? 'Sem permissao para transicao.'),
                ];
            })
            ->values()
            ->all();

        $blockedStatuses = collect($statusOptions)
            ->filter(static fn (array $option): bool => ! $option['is_selectable'])
            ->map(static fn (array $option): array => [
                'label' => $option['label'],
                'reason' => $option['blocked_reason'] ?? 'Sem permissao para transicao.',
            ])
            ->values()
            ->all();

        $operation['product_label'] = ProductType::from((string) $operation['product_type'])->label();
        $operation['requested_value_display'] = $this->formatCurrency((float) $operation['requested_value']);

        $operation['installments'] = collect($operation['installments'] ?? [])
            ->map(fn (array $installment): array => $this->formatInstallment($installment))
            ->values()
            ->all();

        $operation['history'] = collect($operation['history'] ?? [])
            ->map(fn (array $historyItem): array => $this->formatHistoryItem($historyItem))
            ->values()
            ->all();

        return [
            'operation' => $operation,
            'statusOptions' => $statusOptions,
            'blockedStatuses' => $blockedStatuses,
            'statusSelectability' => $statusSelectability,
            'statusBlockedReasons' => $statusBlockedReasons,
            'selectedStatus' => $selectedStatus,
            'page' => [
                'title' => 'Detalhe da Operação',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $installment
     * @return array<string, mixed>
     */
    private function formatInstallment(array $installment): array
    {
        $isOverdue = ! (bool) ($installment['paid'] ?? false)
            && Carbon::make($installment['due_date'])?->isPast() === true;

        $installment['due_date_display'] = $this->formatDate((string) ($installment['due_date'] ?? null));
        $installment['paid_at_display'] = $this->formatDate((string) ($installment['paid_at'] ?? null));
        $installment['value_display'] = $this->formatCurrency((float) ($installment['value'] ?? 0));
        $installment['is_overdue'] = $isOverdue;
        $installment['row_class'] = $isOverdue ? 'bg-red-50' : '';
        $installment['status_class'] = $isOverdue ? 'font-semibold text-red-700' : '';
        $installment['status_label'] = (bool) ($installment['paid'] ?? false)
            ? 'Pago'
            : ($isOverdue ? 'Vencida' : 'Em aberto');

        return $installment;
    }

    /**
     * @param  array<string, mixed>  $historyItem
     * @return array<string, mixed>
     */
    private function formatHistoryItem(array $historyItem): array
    {
        $historyItem['changed_at_display'] = $this->formatDate((string) ($historyItem['changed_at'] ?? null));
        $historyItem['previous_status_label'] = $this->statusLabel($historyItem['previous_status'] ?? null);
        $historyItem['new_status_label'] = $this->statusLabel($historyItem['new_status'] ?? null);

        return $historyItem;
    }

    private function formatCurrency(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }

    private function formatDate(?string $date): string
    {
        return Carbon::make($date)?->format('d/m/Y') ?? '-';
    }

    private function statusLabel(?string $statusValue): string
    {
        if ($statusValue === null || $statusValue === '') {
            return '-';
        }

        try {
            return OperationStatus::from($statusValue)->label();
        } catch (\ValueError) {
            return $statusValue;
        }
    }
}
