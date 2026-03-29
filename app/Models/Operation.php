<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\ProductType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'client_id',
    'agreement_id',
    'requested_value',
    'disbursement_value',
    'total_interest',
    'late_fee_rate',
    'late_interest_rate',
    'installments_count',
    'paid_installments_count',
    'installment_value',
    'status',
    'product_type',
    'first_due_date',
    'proposal_created_date',
    'payment_date',
])]
class Operation extends Model
{
    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Agreement, $this>
     */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    /**
     * @return HasMany<Installment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class);
    }

    /**
     * @return HasMany<OperationStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OperationStatusHistory::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_value' => 'float',
            'disbursement_value' => 'float',
            'total_interest' => 'float',
            'late_fee_rate' => 'float',
            'late_interest_rate' => 'float',
            'installment_value' => 'float',
            'status' => OperationStatus::class,
            'product_type' => ProductType::class,
            'first_due_date' => 'date',
            'proposal_created_date' => 'date',
            'payment_date' => 'datetime',
        ];
    }
}
