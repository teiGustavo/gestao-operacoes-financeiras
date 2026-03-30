<?php

declare(strict_types=1);

namespace App\Infrastructure\Queries\Operation;

use App\Models\Operation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

final class OperationReportCsvQuery
{
    /**
     * @param  array{status?: string, operation?: int, product?: string, agreement?: int}  $filters
     * @return LazyCollection<int, Operation>
     */
    public function cursor(array $filters): LazyCollection
    {
        return $this->baseQuery($filters)
            ->orderBy('id')
            ->lazyById(chunkSize: 300);
    }

    /**
     * @param  array{status?: string, operation?: int, product?: string, agreement?: int}  $filters
     */
    public function count(array $filters): int
    {
        return (int) $this->idBaseQuery($filters)->count('id');
    }

    /**
     * @param  array{status?: string, operation?: int, product?: string, agreement?: int}  $filters
     * @return LazyCollection<int, int>
     */
    public function operationIdCursor(array $filters): LazyCollection
    {
        return $this->idBaseQuery($filters)
            ->orderBy('id')
            ->lazyById(chunkSize: 1_000)
            ->map(static fn (Operation $operation): int => $operation->id);
    }

    /**
     * @param  array{status?: string, operation?: int, product?: string, agreement?: int}  $filters
     * @return LazyCollection<int, Operation>
     */
    public function cursorByIdRange(array $filters, int $startOperationId, int $endOperationId): LazyCollection
    {
        return $this->baseQuery($filters)
            ->whereBetween('id', [$startOperationId, $endOperationId])
            ->orderBy('id')
            ->lazyById(chunkSize: 300);
    }

    /**
     * @param  array{status?: string, operation?: int, product?: string, agreement?: int}  $filters
     * @return Builder<Operation>
     */
    private function baseQuery(array $filters): Builder
    {
        $query = $this->idBaseQuery($filters)
            ->select([
                'id',
                'client_id',
                'agreement_id',
                'requested_value',
                'total_interest',
                'late_fee_rate',
                'late_interest_rate',
                'status',
                'product_type',
            ])
            ->with([
                'client:id,name,cpf',
                'agreement:id,name',
                'installments' => static function ($installmentsQuery): void {
                    $installmentsQuery
                        ->select(['id', 'operation_id', 'due_date', 'value', 'paid'])
                        ->where('paid', false)
                        ->orderBy('installment_number');
                },
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

        return $query;
    }

    /**
     * @param  array{status?: string, operation?: int, product?: string, agreement?: int}  $filters
     * @return Builder<Operation>
     */
    private function idBaseQuery(array $filters): Builder
    {
        $query = Operation::query();

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

        return $query;
    }
}
