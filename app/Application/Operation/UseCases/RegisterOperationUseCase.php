<?php

declare(strict_types=1);

namespace App\Application\Operation\UseCases;

use App\Application\Operation\Data\RegisterOperationInput;
use App\Application\Operation\Data\RegisterOperationOutput;
use App\Domain\Operation\Contracts\Repositories\OperationRepositoryInterface;
use App\Domain\Operation\Services\OperationLifecycleService;
use App\Domain\Shared\Result\Result;

final readonly class RegisterOperationUseCase
{
    public function __construct(
        private OperationRepositoryInterface $operationRepository,
        private OperationLifecycleService $operationLifecycleService,
    ) {}

    /**
     * @return Result<RegisterOperationOutput>
     */
    public function execute(RegisterOperationInput $input): Result
    {
        $operationResult = $this->operationLifecycleService->buildOperationForCreation(
            clientId: $input->clientId,
            agreementId: $input->agreementId,
            requestedValue: $input->requestedValue,
            disbursementValue: $input->disbursementValue,
            totalInterest: $input->totalInterest,
            lateFeeRate: $input->lateFeeRate,
            lateInterestRate: $input->lateInterestRate,
            installmentsCount: $input->installmentsCount,
            paidInstallmentsCount: $input->paidInstallmentsCount,
            installmentValue: $input->installmentValue,
            status: $input->status,
            productType: $input->productType,
            firstDueDate: $input->firstDueDate,
            proposalCreatedDate: $input->proposalCreatedDate,
            paymentDate: $input->paymentDate,
        );

        if ($operationResult->isFailure()) {
            return Result::failure(...$operationResult->errors());
        }

        $savedOperation = $this->operationRepository->save($operationResult->value());

        return Result::success(RegisterOperationOutput::fromOperation($savedOperation));
    }
}
