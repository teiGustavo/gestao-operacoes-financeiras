<?php

declare(strict_types=1);

namespace App\Infrastructure\Queries\Operation;

use App\Models\Operation;
use Illuminate\Support\LazyCollection;

final class OperationReportCsvQuery
{
    /**
     * @param  array{status?: string, operation?: int, product?: string, agreement?: int}  $filters
     * @return LazyCollection<int, Operation>
     */
    public function cursor(array $filters): LazyCollection
    {
        $query = Operation::query()
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

        return $query
            ->orderBy('id')
            ->lazyById(chunkSize: 300);
    }
}
