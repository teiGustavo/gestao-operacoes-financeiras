<?php

declare(strict_types=1);

namespace App\Infrastructure\Queries\Operation;

use App\Models\Operation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class OperationListQuery
{
    /**
     * @param  array{status?: string, operation?: int, product?: string, agreement?: int, per_page?: int}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $perPage = $filters['per_page'] ?? 15;

        $query = Operation::query()
            ->select([
                'id',
                'client_id',
                'agreement_id',
                'requested_value',
                'status',
                'product_type',
            ])
            ->with([
                'client:id,name,cpf',
                'agreement:id,name',
            ]);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['operation'])) {
            $query->where('id', $filters['operation']);
        }

        if (isset($filters['product'])) {
            $query->where('product_type', $filters['product']);
        }

        if (isset($filters['agreement'])) {
            $query->where('agreement_id', $filters['agreement']);
        }

        return $query
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(static function (Operation $operation): array {
                return [
                    'operation_code' => $operation->id,
                    'client_name' => $operation->client?->name,
                    'cpf' => $operation->client?->cpf,
                    'operation_value' => (float) $operation->requested_value,
                    'status' => [
                        'value' => $operation->status->value,
                        'label' => $operation->status->label(),
                    ],
                    'product' => [
                        'value' => $operation->product_type->value,
                        'label' => $operation->product_type->label(),
                    ],
                    'agreement' => [
                        'id' => $operation->agreement_id,
                        'name' => $operation->agreement?->name,
                    ],
                ];
            });
    }
}
