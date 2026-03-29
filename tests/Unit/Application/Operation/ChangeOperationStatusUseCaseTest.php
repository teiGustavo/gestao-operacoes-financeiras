<?php

declare(strict_types=1);

use App\Application\Operation\Data\ChangeOperationStatusInput;
use App\Application\Operation\Data\RegisterOperationInput;
use App\Application\Operation\UseCases\ChangeOperationStatusUseCase;
use App\Application\Operation\UseCases\RegisterOperationUseCase;
use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\ProductType;
use App\Domain\Operation\Services\OperationLifecycleService;
use App\Domain\Shared\Result\ErrorCode;
use Tests\Support\InMemoryOperationRepository;
use Tests\Support\InMemoryOperationStatusHistoryRepository;

it('returns not found when operation does not exist', function () {
    $useCase = new ChangeOperationStatusUseCase(
        operationRepository: new InMemoryOperationRepository,
        operationStatusHistoryRepository: new InMemoryOperationStatusHistoryRepository,
        operationLifecycleService: new OperationLifecycleService,
    );

    $result = $useCase->execute(new ChangeOperationStatusInput(
        operationId: 999,
        newStatus: OperationStatus::APPROVED,
        changedByUserId: 1,
        notes: null,
        paymentDate: null,
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::OperationNotFound);
});

it('changes status and appends history when transition is valid', function () {
    $operationRepository = new InMemoryOperationRepository;
    $historyRepository = new InMemoryOperationStatusHistoryRepository;
    $lifecycleService = new OperationLifecycleService;

    $registerUseCase = new RegisterOperationUseCase($operationRepository, $lifecycleService);

    $registerResult = $registerUseCase->execute(new RegisterOperationInput(
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
        status: OperationStatus::APPROVED,
        productType: ProductType::PAYROLL_LOAN,
        firstDueDate: '2026-06-01',
        proposalCreatedDate: '2026-05-01',
        paymentDate: null,
    ));

    $useCase = new ChangeOperationStatusUseCase(
        operationRepository: $operationRepository,
        operationStatusHistoryRepository: $historyRepository,
        operationLifecycleService: $lifecycleService,
    );

    $result = $useCase->execute(new ChangeOperationStatusInput(
        operationId: $registerResult->value()->id,
        newStatus: OperationStatus::DISBURSED,
        changedByUserId: 17,
        notes: 'Pagamento concluido',
        paymentDate: '2026-05-02',
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->status)->toBe(OperationStatus::DISBURSED);

    $history = $historyRepository->listByOperationId($registerResult->value()->id);

    expect($history)->toHaveCount(1)
        ->and($history[0]->previousStatus)->toBe(OperationStatus::APPROVED)
        ->and($history[0]->newStatus)->toBe(OperationStatus::DISBURSED);
});
