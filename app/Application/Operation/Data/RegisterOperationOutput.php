<?php

declare(strict_types=1);

namespace App\Application\Operation\Data;

use App\Domain\Operation\Entities\Operation;
use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\ProductType;

final readonly class RegisterOperationOutput
{
    public function __construct(
        public int $id,
        public int $clientId,
        public int $agreementId,
        public OperationStatus $status,
        public ProductType $productType,
        public string $proposalCreatedDate,
        public ?string $paymentDate,
    ) {}

    public static function fromOperation(Operation $operation): self
    {
        return new self(
            id: (int) $operation->id,
            clientId: $operation->clientId,
            agreementId: $operation->agreementId,
            status: $operation->status,
            productType: $operation->productType,
            proposalCreatedDate: $operation->proposalCreatedDate->format('Y-m-d'),
            paymentDate: $operation->paymentDate?->format('Y-m-d'),
        );
    }
}
