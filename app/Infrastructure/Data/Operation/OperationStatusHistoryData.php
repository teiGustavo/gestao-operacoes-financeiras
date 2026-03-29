<?php

declare(strict_types=1);

namespace App\Infrastructure\Data\Operation;

use Spatie\LaravelData\Data;

final class OperationStatusHistoryData extends Data
{
    public function __construct(
        public ?int $id,
        public int $operationId,
        public ?string $previousStatus,
        public string $newStatus,
        public int $changedByUserId,
        public ?string $notes,
        public string $changedAt,
    ) {
    }
}

