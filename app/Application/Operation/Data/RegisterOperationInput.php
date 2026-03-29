<?php

declare(strict_types=1);

namespace App\Application\Operation\Data;

use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\ProductType;

final readonly class RegisterOperationInput
{
    public function __construct(
        public int $clientId,
        public int $agreementId,
        public float $requestedValue,
        public float $disbursementValue,
        public float $totalInterest,
        public float $lateFeeRate,
        public float $lateInterestRate,
        public int $installmentsCount,
        public int $paidInstallmentsCount,
        public float $installmentValue,
        public OperationStatus $status,
        public ProductType $productType,
        public string $firstDueDate,
        public string $proposalCreatedDate,
        public ?string $paymentDate,
    ) {}
}
