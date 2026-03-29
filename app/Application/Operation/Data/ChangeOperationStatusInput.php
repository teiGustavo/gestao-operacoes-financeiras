<?php

declare(strict_types=1);

namespace App\Application\Operation\Data;

use App\Domain\Operation\OperationStatus;

final readonly class ChangeOperationStatusInput
{
    public function __construct(
        public int $operationId,
        public OperationStatus $newStatus,
        public int $changedByUserId,
        public ?string $notes,
        public ?string $paymentDate,
    ) {}
}
