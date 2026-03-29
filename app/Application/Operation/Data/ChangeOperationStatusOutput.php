<?php

declare(strict_types=1);

namespace App\Application\Operation\Data;

use App\Domain\Operation\Entities\Operation;
use App\Domain\Operation\OperationStatus;

final readonly class ChangeOperationStatusOutput
{
    public function __construct(
        public int $operationId,
        public OperationStatus $status,
        public ?string $paymentDate,
    ) {}

    public static function fromOperation(Operation $operation): self
    {
        return new self(
            operationId: (int) $operation->id,
            status: $operation->status,
            paymentDate: $operation->paymentDate?->format('Y-m-d'),
        );
    }
}
