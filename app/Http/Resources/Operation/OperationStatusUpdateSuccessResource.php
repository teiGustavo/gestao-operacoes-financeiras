<?php

declare(strict_types=1);

namespace App\Http\Resources\Operation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array{
 *     message: string,
 *     operation_id: int,
 *     status: string,
 *     payment_date: ?string
 * }
 */
final class OperationStatusUpdateSuccessResource extends JsonResource
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
                'status' => $this['status'],
                'payment_date' => $this['payment_date'],
            ],
        ];
    }
}
