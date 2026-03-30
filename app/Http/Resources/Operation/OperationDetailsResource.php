<?php

declare(strict_types=1);

namespace App\Http\Resources\Operation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array<string, mixed>
 */
final class OperationDetailsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this['id'],
            'client' => $this['client'],
            'agreement' => $this['agreement'],
            'status' => $this['status'],
            'product_type' => $this['product_type'],
            'requested_value' => $this['requested_value'],
            'disbursement_value' => $this['disbursement_value'],
            'total_interest' => $this['total_interest'],
            'installments_count' => $this['installments_count'],
            'paid_installments_count' => $this['paid_installments_count'],
            'installment_value' => $this['installment_value'],
            'first_due_date' => $this['first_due_date'],
            'proposal_created_date' => $this['proposal_created_date'],
            'payment_date' => $this['payment_date'],
            'installments' => $this['installments'],
            'history' => $this['history'],
        ];
    }
}
