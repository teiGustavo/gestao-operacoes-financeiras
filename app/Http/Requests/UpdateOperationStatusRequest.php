<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Operation\OperationStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOperationStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(array_map(
                static fn (OperationStatus $status): string => $status->value,
                OperationStatus::cases(),
            ))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_date' => ['nullable', 'date_format:Y-m-d'],
            'redirect_to' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
