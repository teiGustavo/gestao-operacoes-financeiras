<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\ProductType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListOperationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(array_map(
                static fn (OperationStatus $status): string => $status->value,
                OperationStatus::cases(),
            ))],
            'operation' => ['nullable', 'integer', 'min:1'],
            'product' => ['nullable', 'string', Rule::in(array_map(
                static fn (ProductType $productType): string => $productType->value,
                ProductType::cases(),
            ))],
            'agreement' => ['nullable', 'integer', 'min:1', 'exists:agreements,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
