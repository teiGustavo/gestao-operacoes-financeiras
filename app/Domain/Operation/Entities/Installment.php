<?php

declare(strict_types=1);

namespace App\Domain\Operation\Entities;

use DateTimeImmutable;

final readonly class Installment
{
    public function __construct(
        public ?int $id,
        public int $operationId,
        public int $installmentNumber,
        public DateTimeImmutable $dueDate,
        public float $value,
        public bool $paid,
        public ?DateTimeImmutable $paidAt,
        public ?int $paidByUserId,
    ) {}
}
