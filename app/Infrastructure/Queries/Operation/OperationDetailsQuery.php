<?php

declare(strict_types=1);

namespace App\Infrastructure\Queries\Operation;

use App\Models\Installment;
use App\Models\Operation;
use App\Models\OperationStatusHistory;

final class OperationDetailsQuery
{
    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $operationId): ?array
    {
        $operation = Operation::query()
            ->with([
                'client:id,name,cpf,email',
                'agreement:id,name',
                'installments' => fn ($query) => $query
                    ->with('paidByUser:id,name,email')
                    ->orderBy('installment_number'),
                'statusHistories' => fn ($query) => $query
                    ->with('changedByUser:id,name,email')
                    ->orderByDesc('changed_at'),
            ])
            ->find($operationId);

        if ($operation === null) {
            return null;
        }

        return [
            'id' => $operation->id,
            'client' => [
                'id' => $operation->client_id,
                'name' => $operation->client?->name,
                'cpf' => $operation->client?->cpf,
                'email' => $operation->client?->email,
            ],
            'agreement' => [
                'id' => $operation->agreement_id,
                'name' => $operation->agreement?->name,
            ],
            'status' => [
                'value' => $operation->status->value,
                'label' => $operation->status->label(),
            ],
            'product_type' => $operation->product_type->value,
            'requested_value' => (float) $operation->requested_value,
            'disbursement_value' => (float) $operation->disbursement_value,
            'total_interest' => (float) $operation->total_interest,
            'installments_count' => (int) $operation->installments_count,
            'paid_installments_count' => (int) $operation->paid_installments_count,
            'installment_value' => (float) $operation->installment_value,
            'first_due_date' => $operation->first_due_date?->format('Y-m-d'),
            'proposal_created_date' => $operation->proposal_created_date?->format('Y-m-d'),
            'payment_date' => $operation->payment_date?->format('Y-m-d'),
            'installments' => $operation->installments
                ->map(static function (Installment $installment): array {
                    return [
                        'id' => $installment->id,
                        'installment_number' => (int) $installment->installment_number,
                        'due_date' => $installment->due_date?->format('Y-m-d'),
                        'value' => (float) $installment->value,
                        'paid' => (bool) $installment->paid,
                        'paid_at' => $installment->paid_at?->format('Y-m-d H:i:s'),
                        'paid_by_user' => [
                            'id' => $installment->paid_by_user_id,
                            'name' => $installment->paidByUser?->name,
                            'email' => $installment->paidByUser?->email,
                        ],
                    ];
                })
                ->values()
                ->all(),
            'history' => $operation->statusHistories
                ->map(static function (OperationStatusHistory $history): array {
                    return [
                        'id' => $history->id,
                        'previous_status' => $history->previous_status?->value,
                        'new_status' => $history->new_status->value,
                        'notes' => $history->notes,
                        'changed_at' => $history->changed_at?->format('Y-m-d H:i:s'),
                        'changed_by_user' => [
                            'id' => $history->changed_by_user_id,
                            'name' => $history->changedByUser?->name,
                            'email' => $history->changedByUser?->email,
                        ],
                    ];
                })
                ->values()
                ->all(),
        ];
    }
}
