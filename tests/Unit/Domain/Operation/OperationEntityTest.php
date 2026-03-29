<?php

declare(strict_types=1);

use App\Domain\Operation\Entities\Operation;
use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\ProductType;

it('creates a new operation with id while preserving original fields', function () {
    $operation = buildOperationEntity(
        id: null,
        status: OperationStatus::DRAFT,
        paymentDate: null,
    );

    $operationWithId = $operation->withId(77);

    expect($operationWithId)->not->toBe($operation)
        ->and($operationWithId->id)->toBe(77)
        ->and($operationWithId->clientId)->toBe(10)
        ->and($operationWithId->agreementId)->toBe(20)
        ->and($operationWithId->requestedValue)->toBe(1000.0)
        ->and($operationWithId->status)->toBe(OperationStatus::DRAFT)
        ->and($operationWithId->paymentDate)->toBeNull();
});

it('creates a new operation with updated status and payment date', function () {
    $operation = buildOperationEntity(
        id: 55,
        status: OperationStatus::APPROVED,
        paymentDate: null,
    );

    $updatedOperation = $operation->withStatus(
        status: OperationStatus::DISBURSED,
        paymentDate: new DateTimeImmutable('2026-05-02'),
    );

    expect($updatedOperation)->not->toBe($operation)
        ->and($updatedOperation->id)->toBe(55)
        ->and($updatedOperation->status)->toBe(OperationStatus::DISBURSED)
        ->and($updatedOperation->paymentDate?->format('Y-m-d'))->toBe('2026-05-02')
        ->and($updatedOperation->requestedValue)->toBe(1000.0);
});

function buildOperationEntity(?int $id, OperationStatus $status, ?DateTimeImmutable $paymentDate): Operation
{
    return new Operation(
        id: $id,
        clientId: 10,
        agreementId: 20,
        requestedValue: 1000.0,
        disbursementValue: 950.0,
        totalInterest: 50.0,
        lateFeeRate: 2.0,
        lateInterestRate: 1.0,
        installmentsCount: 10,
        paidInstallmentsCount: 0,
        installmentValue: 105.0,
        status: $status,
        productType: ProductType::PAYROLL_LOAN,
        firstDueDate: new DateTimeImmutable('2026-06-01'),
        proposalCreatedDate: new DateTimeImmutable('2026-05-01'),
        paymentDate: $paymentDate,
    );
}

