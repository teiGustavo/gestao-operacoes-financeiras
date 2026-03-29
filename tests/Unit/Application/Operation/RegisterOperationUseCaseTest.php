<?php

declare(strict_types=1);

use App\Application\Operation\Data\RegisterOperationInput;
use App\Application\Operation\UseCases\RegisterOperationUseCase;
use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\ProductType;
use App\Domain\Operation\Services\OperationLifecycleService;
use App\Domain\Shared\Result\ErrorCode;
use Tests\Support\InMemoryOperationRepository;

it('returns failure result when operation payload violates business rules', function () {
    $useCase = new RegisterOperationUseCase(new InMemoryOperationRepository, new OperationLifecycleService);

    $result = $useCase->execute(new RegisterOperationInput(
        clientId: 1,
        agreementId: 1,
        requestedValue: -10,
        disbursementValue: 0,
        totalInterest: 0,
        lateFeeRate: 0,
        lateInterestRate: 0,
        installmentsCount: 10,
        paidInstallmentsCount: 0,
        installmentValue: 10,
        status: OperationStatus::DRAFT,
        productType: ProductType::PERSONAL_LOAN,
        firstDueDate: '2026-06-01',
        proposalCreatedDate: '2026-05-01',
        paymentDate: null,
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::OperationRequestedValueInvalid);
});

it('returns operation output when registering with valid payload', function () {
    $useCase = new RegisterOperationUseCase(new InMemoryOperationRepository, new OperationLifecycleService);

    $result = $useCase->execute(new RegisterOperationInput(
        clientId: 1,
        agreementId: 1,
        requestedValue: 1000,
        disbursementValue: 950,
        totalInterest: 50,
        lateFeeRate: 2,
        lateInterestRate: 1,
        installmentsCount: 10,
        paidInstallmentsCount: 0,
        installmentValue: 105,
        status: OperationStatus::DRAFT,
        productType: ProductType::PERSONAL_LOAN,
        firstDueDate: '2026-06-01',
        proposalCreatedDate: '2026-05-01',
        paymentDate: null,
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->id)->toBe(1)
        ->and($result->value()->status)->toBe(OperationStatus::DRAFT);
});
