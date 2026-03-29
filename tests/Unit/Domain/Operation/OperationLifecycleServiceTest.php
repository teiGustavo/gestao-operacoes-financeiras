<?php

declare(strict_types=1);

use App\Domain\Operation\Entities\Operation;
use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\ProductType;
use App\Domain\Operation\Services\OperationLifecycleService;
use App\Domain\Shared\Result\ErrorCode;

it('fails when first due date format is invalid on creation', function () {
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
        status: OperationStatus::DRAFT,
        productType: ProductType::PAYROLL_LOAN,
        firstDueDate: '01/06/2026',
        proposalCreatedDate: '2026-05-01',
        paymentDate: null,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::OperationFirstDueDateInvalid);
});

it('fails when proposal created date format is invalid on creation', function () {
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
        status: OperationStatus::DRAFT,
        productType: ProductType::PAYROLL_LOAN,
        firstDueDate: '2026-06-01',
        proposalCreatedDate: '2026/05/01',
        paymentDate: null,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::OperationProposalDateInvalid);
});

it('fails when payment date format is invalid on creation', function () {
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
        paymentDate: '2026/05/02',
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::OperationPaymentDateInvalid);
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

it('fails when non-disbursed status provides payment date', function () {
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
        status: OperationStatus::APPROVED,
        productType: ProductType::PAYROLL_LOAN,
        firstDueDate: '2026-06-01',
        proposalCreatedDate: '2026-05-01',
        paymentDate: '2026-05-02',
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::OperationPaymentDateForbidden);
});

it('fails when requested value is invalid on creation', function (float $requestedValue) {
    $service = new OperationLifecycleService;

    $result = $service->buildOperationForCreation(
        clientId: 1,
        agreementId: 1,
        requestedValue: $requestedValue,
        disbursementValue: 900,
        totalInterest: 100,
        lateFeeRate: 1,
        lateInterestRate: 1,
        installmentsCount: 10,
        paidInstallmentsCount: 0,
        installmentValue: 100,
        status: OperationStatus::DRAFT,
        productType: ProductType::PAYROLL_LOAN,
        firstDueDate: '2026-06-01',
        proposalCreatedDate: '2026-05-01',
        paymentDate: null,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::OperationRequestedValueInvalid);
})->with([
    'zero' => 0.0,
    'negative' => -1.0,
]);

it('fails when disbursement value is invalid on creation', function () {
    $service = new OperationLifecycleService;

    $result = $service->buildOperationForCreation(
        clientId: 1,
        agreementId: 1,
        requestedValue: 1000,
        disbursementValue: -0.01,
        totalInterest: 100,
        lateFeeRate: 1,
        lateInterestRate: 1,
        installmentsCount: 10,
        paidInstallmentsCount: 0,
        installmentValue: 100,
        status: OperationStatus::DRAFT,
        productType: ProductType::PAYROLL_LOAN,
        firstDueDate: '2026-06-01',
        proposalCreatedDate: '2026-05-01',
        paymentDate: null,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::OperationDisbursementValueInvalid);
});

it('fails when total interest is invalid on creation', function () {
    $service = new OperationLifecycleService;

    $result = $service->buildOperationForCreation(
        clientId: 1,
        agreementId: 1,
        requestedValue: 1000,
        disbursementValue: 900,
        totalInterest: -0.01,
        lateFeeRate: 1,
        lateInterestRate: 1,
        installmentsCount: 10,
        paidInstallmentsCount: 0,
        installmentValue: 100,
        status: OperationStatus::DRAFT,
        productType: ProductType::PAYROLL_LOAN,
        firstDueDate: '2026-06-01',
        proposalCreatedDate: '2026-05-01',
        paymentDate: null,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::OperationTotalInterestInvalid);
});

it('fails when late rates are invalid on creation', function (float $lateFeeRate, float $lateInterestRate) {
    $service = new OperationLifecycleService;

    $result = $service->buildOperationForCreation(
        clientId: 1,
        agreementId: 1,
        requestedValue: 1000,
        disbursementValue: 900,
        totalInterest: 100,
        lateFeeRate: $lateFeeRate,
        lateInterestRate: $lateInterestRate,
        installmentsCount: 10,
        paidInstallmentsCount: 0,
        installmentValue: 100,
        status: OperationStatus::DRAFT,
        productType: ProductType::PAYROLL_LOAN,
        firstDueDate: '2026-06-01',
        proposalCreatedDate: '2026-05-01',
        paymentDate: null,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::OperationLateRatesInvalid);
})->with([
    'negative late fee' => [-1.0, 1.0],
    'negative late interest' => [1.0, -1.0],
]);

it('fails when installments count is invalid on creation', function () {
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

it('fails when paid installments count is invalid on creation', function (int $paidInstallmentsCount) {
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
        paidInstallmentsCount: $paidInstallmentsCount,
        installmentValue: 100,
        status: OperationStatus::DRAFT,
        productType: ProductType::PAYROLL_LOAN,
        firstDueDate: '2026-06-01',
        proposalCreatedDate: '2026-05-01',
        paymentDate: null,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::OperationPaidInstallmentsCountInvalid);
})->with([
    'negative paid installments' => -1,
    'greater than installments count' => 11,
]);

it('fails when installment value is invalid on creation', function (float $installmentValue) {
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
        installmentValue: $installmentValue,
        status: OperationStatus::DRAFT,
        productType: ProductType::PAYROLL_LOAN,
        firstDueDate: '2026-06-01',
        proposalCreatedDate: '2026-05-01',
        paymentDate: null,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::OperationInstallmentValueInvalid);
})->with([
    'zero' => 0.0,
    'negative' => -1.0,
]);

it('builds operation when creation payload is valid', function () {
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
        status: OperationStatus::DRAFT,
        productType: ProductType::PAYROLL_LOAN,
        firstDueDate: '2026-06-01',
        proposalCreatedDate: '2026-05-01',
        paymentDate: null,
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->status)->toBe(OperationStatus::DRAFT)
        ->and($result->value()->paymentDate)->toBeNull();
});

it('builds disbursed operation when payment date is provided', function () {
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
        paymentDate: '2026-05-02',
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->status)->toBe(OperationStatus::DISBURSED)
        ->and($result->value()->paymentDate?->format('Y-m-d'))->toBe('2026-05-02');
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

it('updates operation through the full valid status sequence until disbursed', function () {
    $service = new OperationLifecycleService;

    $operation = operationWithStatus($service, OperationStatus::DRAFT);

    $transitions = [
        [OperationStatus::PRE_ANALYSIS, null],
        [OperationStatus::UNDER_REVIEW, null],
        [OperationStatus::AWAITING_SIGNATURE, null],
        [OperationStatus::SIGNATURE_COMPLETED, null],
        [OperationStatus::APPROVED, null],
        [OperationStatus::DISBURSED, '2026-05-10'],
    ];

    foreach ($transitions as [$newStatus, $paymentDate]) {
        $changeResult = $service->changeStatus($operation, $newStatus, $paymentDate);

        expect($changeResult->isSuccess())->toBeTrue();

        $operation = $changeResult->value();
    }

    expect($operation->status)->toBe(OperationStatus::DISBURSED)
        ->and($operation->paymentDate?->format('Y-m-d'))->toBe('2026-05-10');
});

it('allows cancellation from statuses that permit cancellation', function (OperationStatus $initialStatus) {
    $service = new OperationLifecycleService;
    $operation = operationWithStatus($service, $initialStatus);

    $changeResult = $service->changeStatus($operation, OperationStatus::CANCELED, null);

    expect($changeResult->isSuccess())->toBeTrue()
        ->and($changeResult->value()->status)->toBe(OperationStatus::CANCELED)
        ->and($changeResult->value()->paymentDate)->toBeNull();
})->with([
    'from pre analysis' => OperationStatus::PRE_ANALYSIS,
    'from under review' => OperationStatus::UNDER_REVIEW,
    'from awaiting signature' => OperationStatus::AWAITING_SIGNATURE,
    'from signature completed' => OperationStatus::SIGNATURE_COMPLETED,
    'from approved' => OperationStatus::APPROVED,
]);

it('fails when changing to the same status', function () {
    $service = new OperationLifecycleService;
    $operation = operationWithStatus($service, OperationStatus::PRE_ANALYSIS);

    $changeResult = $service->changeStatus($operation, OperationStatus::PRE_ANALYSIS, null);

    expect($changeResult->isFailure())->toBeTrue()
        ->and($changeResult->firstError()?->code)->toBe(ErrorCode::OperationStatusUnchanged);
});

it('fails when transition is invalid', function (OperationStatus $initialStatus, OperationStatus $newStatus) {
    $service = new OperationLifecycleService;
    $operation = operationWithStatus($service, $initialStatus);

    $changeResult = $service->changeStatus($operation, $newStatus, null);

    expect($changeResult->isFailure())->toBeTrue()
        ->and($changeResult->firstError()?->code)->toBe(ErrorCode::OperationStatusTransitionInvalid)
        ->and($changeResult->firstError()?->context)->toBe([
            'current_status' => $initialStatus->value,
            'new_status' => $newStatus->value,
        ]);
})->with([
    'draft skipping to under review' => [OperationStatus::DRAFT, OperationStatus::UNDER_REVIEW],
    'pre analysis skipping to approved' => [OperationStatus::PRE_ANALYSIS, OperationStatus::APPROVED],
    'under review jumping to disbursed' => [OperationStatus::UNDER_REVIEW, OperationStatus::DISBURSED],
]);

it('fails when trying to change status after disbursed', function () {
    $service = new OperationLifecycleService;
    $operation = operationWithStatus($service, OperationStatus::DISBURSED, '2026-05-02');

    $changeResult = $service->changeStatus($operation, OperationStatus::CANCELED, null);

    expect($changeResult->isFailure())->toBeTrue()
        ->and($changeResult->firstError()?->code)->toBe(ErrorCode::OperationStatusTransitionInvalid);
});

it('fails when trying to change status after canceled', function () {
    $service = new OperationLifecycleService;
    $operation = operationWithStatus($service, OperationStatus::CANCELED);

    $changeResult = $service->changeStatus($operation, OperationStatus::PRE_ANALYSIS, null);

    expect($changeResult->isFailure())->toBeTrue()
        ->and($changeResult->firstError()?->code)->toBe(ErrorCode::OperationStatusTransitionInvalid);
});

it('fails when disbursing without payment date during status change', function () {
    $service = new OperationLifecycleService;
    $operation = operationWithStatus($service, OperationStatus::APPROVED);

    $changeResult = $service->changeStatus($operation, OperationStatus::DISBURSED, null);

    expect($changeResult->isFailure())->toBeTrue()
        ->and($changeResult->firstError()?->code)->toBe(ErrorCode::OperationPaymentDateRequired);
});

it('fails when non-disbursed status change receives payment date', function () {
    $service = new OperationLifecycleService;
    $operation = operationWithStatus($service, OperationStatus::DRAFT);

    $changeResult = $service->changeStatus($operation, OperationStatus::PRE_ANALYSIS, '2026-05-02');

    expect($changeResult->isFailure())->toBeTrue()
        ->and($changeResult->firstError()?->code)->toBe(ErrorCode::OperationPaymentDateForbidden);
});

function operationWithStatus(
    OperationLifecycleService $service,
    OperationStatus $status,
    ?string $paymentDate = null,
): Operation {
    return $service->buildOperationForCreation(
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
        status: $status,
        productType: ProductType::PAYROLL_LOAN,
        firstDueDate: '2026-06-01',
        proposalCreatedDate: '2026-05-01',
        paymentDate: $paymentDate,
    )->value();
}
