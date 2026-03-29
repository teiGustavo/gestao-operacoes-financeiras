<?php

declare(strict_types=1);

use App\Domain\Operation\Entities\Operation;
use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\ProductType;
use App\Infrastructure\Data\Operation\OperationData;
use App\Infrastructure\Repositories\Mappers\Operation\OperationDataMapper;

it('maps operation domain entity to persistence data and back', function () {
    $mapper = new OperationDataMapper;

    $operation = new Operation(
        id: 11,
        clientId: 3,
        agreementId: 7,
        requestedValue: 1000,
        disbursementValue: 950,
        totalInterest: 50,
        lateFeeRate: 2,
        lateInterestRate: 1,
        installmentsCount: 10,
        paidInstallmentsCount: 0,
        installmentValue: 105,
        status: OperationStatus::DRAFT,
        productType: ProductType::PAYROLL_LOAN,
        firstDueDate: new DateTimeImmutable('2026-06-01'),
        proposalCreatedDate: new DateTimeImmutable('2026-05-01'),
        paymentDate: null,
    );

    $persistenceData = $mapper->toPersistence($operation);

    expect($persistenceData)->toBeInstanceOf(OperationData::class)
        ->and($persistenceData->status)->toBe(OperationStatus::DRAFT->value)
        ->and($persistenceData->productType)->toBe(ProductType::PAYROLL_LOAN->value);

    $mappedOperation = $mapper->toDomain($persistenceData);

    expect($mappedOperation)->toBeInstanceOf(Operation::class)
        ->and($mappedOperation->id)->toBe(11)
        ->and($mappedOperation->firstDueDate->format('Y-m-d'))->toBe('2026-06-01');
});
