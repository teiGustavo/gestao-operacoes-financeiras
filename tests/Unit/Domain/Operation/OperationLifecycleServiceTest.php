<?php

declare(strict_types=1);

use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\ProductType;
use App\Domain\Operation\Services\OperationLifecycleService;
use App\Domain\Shared\Result\ErrorCode;

it('fails when registering operation with invalid installments count', function () {
    $service = new OperationLifecycleService;

    $result = $service->buildOperationForCreation(
        clientId: 1,
        agreementId: 1,
        requestedValue: 1000,
        disbursementValue: 900,
        totalInterest: 100,
        lateFeeRate: 1,
        lateInterestRate: 1,
        installmentsCount: 0,
        paidInstallmentsCount: 0,
        installmentValue: 100,
        status: OperationStatus::DRAFT,
        productType: ProductType::PAYROLL_LOAN,
        firstDueDate: '2026-06-01',
        proposalCreatedDate: '2026-05-01',
        paymentDate: null,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::OperationInstallmentsCountInvalid);
});

it('fails when disbursed status does not provide payment date', function () {
    $service = new OperationLifecycleService;

    $result = $service->buildOperationForCreation(
        clientId: 1,
        agreementId: 1,
        requestedValue: 1000,
        disbursementValue: 900,
        totalInterest: 100,
        lateFeeRate: 1,
        lateInterestRate: 1,
        installmentsCount: 10,
        paidInstallmentsCount: 0,
        installmentValue: 100,
        status: OperationStatus::DISBURSED,
        productType: ProductType::PAYROLL_LOAN,
        firstDueDate: '2026-06-01',
        proposalCreatedDate: '2026-05-01',
        paymentDate: null,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::OperationPaymentDateRequired);
});

it('updates operation status when transition is valid', function () {
    $service = new OperationLifecycleService;

    $operation = $service->buildOperationForCreation(
        clientId: 1,
        agreementId: 1,
        requestedValue: 1000,
        disbursementValue: 900,
        totalInterest: 100,
        lateFeeRate: 1,
        lateInterestRate: 1,
        installmentsCount: 10,
        paidInstallmentsCount: 0,
        installmentValue: 100,
        status: OperationStatus::APPROVED,
        productType: ProductType::PAYROLL_LOAN,
        firstDueDate: '2026-06-01',
        proposalCreatedDate: '2026-05-01',
        paymentDate: null,
    )->value();

    $statusChangeResult = $service->changeStatus($operation, OperationStatus::DISBURSED, '2026-05-02');

    expect($statusChangeResult->isSuccess())->toBeTrue()
        ->and($statusChangeResult->value()->status)->toBe(OperationStatus::DISBURSED)
        ->and($statusChangeResult->value()->paymentDate?->format('Y-m-d'))->toBe('2026-05-02');
});
