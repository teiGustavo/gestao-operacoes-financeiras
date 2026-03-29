<?php

declare(strict_types=1);

namespace App\Domain\Operation\Entities;

use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\ProductType;
use DateTimeImmutable;

final readonly class Operation
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
        public OperationStatus $status,
        public ProductType $productType,
        public DateTimeImmutable $firstDueDate,
        public DateTimeImmutable $proposalCreatedDate,
        public ?DateTimeImmutable $paymentDate,
    ) {}

    public function withId(int $id): self
    {
        return new self(
            id: $id,
            clientId: $this->clientId,
            agreementId: $this->agreementId,
            requestedValue: $this->requestedValue,
            disbursementValue: $this->disbursementValue,
            totalInterest: $this->totalInterest,
            lateFeeRate: $this->lateFeeRate,
            lateInterestRate: $this->lateInterestRate,
            installmentsCount: $this->installmentsCount,
            paidInstallmentsCount: $this->paidInstallmentsCount,
            installmentValue: $this->installmentValue,
            status: $this->status,
            productType: $this->productType,
            firstDueDate: $this->firstDueDate,
            proposalCreatedDate: $this->proposalCreatedDate,
            paymentDate: $this->paymentDate,
        );
    }

    public function withStatus(OperationStatus $status, ?DateTimeImmutable $paymentDate): self
    {
        return new self(
            id: $this->id,
            clientId: $this->clientId,
            agreementId: $this->agreementId,
            requestedValue: $this->requestedValue,
            disbursementValue: $this->disbursementValue,
            totalInterest: $this->totalInterest,
            lateFeeRate: $this->lateFeeRate,
            lateInterestRate: $this->lateInterestRate,
            installmentsCount: $this->installmentsCount,
            paidInstallmentsCount: $this->paidInstallmentsCount,
            installmentValue: $this->installmentValue,
            status: $status,
            productType: $this->productType,
            firstDueDate: $this->firstDueDate,
            proposalCreatedDate: $this->proposalCreatedDate,
            paymentDate: $paymentDate,
        );
    }
}
