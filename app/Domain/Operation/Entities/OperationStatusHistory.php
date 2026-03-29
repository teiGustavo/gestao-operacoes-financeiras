<?php

declare(strict_types=1);

namespace App\Domain\Operation\Entities;

use App\Domain\Operation\OperationStatus;
use DateTimeImmutable;

final readonly class OperationStatusHistory
{
    public function __construct(
        public ?int $id,
        public int $operationId,
        public ?OperationStatus $previousStatus,
        public OperationStatus $newStatus,
        public int $changedByUserId,
        public ?string $notes,
        public DateTimeImmutable $changedAt,
    ) {}
}
