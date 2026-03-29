<?php

declare(strict_types=1);

namespace App\Infrastructure\Data\Operation;

use Spatie\LaravelData\Data;

final class OperationData extends Data
{
    public function __construct(
        public ?int $id,
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
        public string $status,
        public string $productType,
        public string $firstDueDate,
        public string $proposalCreatedDate,
        public ?string $paymentDate,
    ) {}
}
