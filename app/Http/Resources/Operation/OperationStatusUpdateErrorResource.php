<?php

declare(strict_types=1);

namespace App\Http\Resources\Operation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array{
 *     message: string,
 *     errors: array<int, array{code: string, message: string, context: array<string, mixed>}>
 * }
 */
final class OperationStatusUpdateErrorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'message' => $this['message'],
            'errors' => $this['errors'],
        ];
    }
}
