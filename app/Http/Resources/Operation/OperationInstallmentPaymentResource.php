<?php

declare(strict_types=1);

namespace App\Http\Resources\Operation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array{
 *     message: string,
 *     operation_id: int,
 *     installment_id: int,
 *     paid: bool
 * }
 */
final class OperationInstallmentPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'message' => $this['message'],
            'data' => [
                'operation_id' => $this['operation_id'],
                'installment_id' => $this['installment_id'],
                'paid' => $this['paid'],
            ],
        ];
    }
}
