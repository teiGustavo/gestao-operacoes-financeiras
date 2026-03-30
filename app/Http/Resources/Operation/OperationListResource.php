<?php

declare(strict_types=1);

namespace App\Http\Resources\Operation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array{
 *     operation_code: int,
 *     client_name: ?string,
 *     cpf: ?string,
 *     operation_value: float,
 *     status: array{value: string, label: string},
 *     product: array{value: string, label: string},
 *     agreement: array{id: int, name: ?string}
 * }
 */
final class OperationListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'operation_code' => $this['operation_code'],
            'client_name' => $this['client_name'],
            'cpf' => $this['cpf'],
            'operation_value' => $this['operation_value'],
            'status' => [
                'value' => $this['status']['value'],
                'label' => $this['status']['label'],
            ],
            'product' => [
                'value' => $this['product']['value'],
                'label' => $this['product']['label'],
            ],
            'agreement' => [
                'id' => $this['agreement']['id'],
                'name' => $this['agreement']['name'],
            ],
        ];
    }
}
