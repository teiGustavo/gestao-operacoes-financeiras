<?php

declare(strict_types=1);

namespace App\Infrastructure\Data\Operation;

use Spatie\LaravelData\Data;

final class InstallmentData extends Data
{
    public function __construct(
        public ?int $id,
        public int $operationId,
        public int $installmentNumber,
        public string $dueDate,
        public float $value,
        public bool $paid,
        public ?string $paidAt,
        public ?int $paidByUserId,
    ) {}
}
