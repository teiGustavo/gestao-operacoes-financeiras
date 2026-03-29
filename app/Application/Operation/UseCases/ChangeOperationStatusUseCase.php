<?php

declare(strict_types=1);

namespace App\Application\Operation\UseCases;

use App\Application\Operation\Data\ChangeOperationStatusInput;
use App\Application\Operation\Data\ChangeOperationStatusOutput;
use App\Domain\Operation\Contracts\Repositories\OperationRepositoryInterface;
use App\Domain\Operation\Contracts\Repositories\OperationStatusHistoryRepositoryInterface;
use App\Domain\Operation\Entities\OperationStatusHistory;
use App\Domain\Operation\Services\OperationLifecycleService;
use App\Domain\Shared\Result\DomainError;
use App\Domain\Shared\Result\ErrorCode;
use App\Domain\Shared\Result\Result;
use DateTimeImmutable;

final readonly class ChangeOperationStatusUseCase
{
    public function __construct(
        private OperationRepositoryInterface $operationRepository,
        private OperationStatusHistoryRepositoryInterface $operationStatusHistoryRepository,
        private OperationLifecycleService $operationLifecycleService,
    ) {}

    /**
     * @return Result<ChangeOperationStatusOutput>
     */
    public function execute(ChangeOperationStatusInput $input): Result
    {
        $operation = $this->operationRepository->findById($input->operationId);

        if ($operation === null) {
            return Result::failure(new DomainError(
                code: ErrorCode::OperationNotFound,
                message: 'Operacao nao encontrada.',
                context: ['operation_id' => $input->operationId],
            ));
        }

        $statusChangeResult = $this->operationLifecycleService->changeStatus(
            operation: $operation,
            newStatus: $input->newStatus,
            paymentDate: $input->paymentDate,
        );

        if ($statusChangeResult->isFailure()) {
            return Result::failure(...$statusChangeResult->errors());
        }

        $updatedOperation = $this->operationRepository->save($statusChangeResult->value());

        $this->operationStatusHistoryRepository->append(new OperationStatusHistory(
            id: null,
            operationId: (int) $updatedOperation->id,
            previousStatus: $operation->status,
            newStatus: $updatedOperation->status,
            changedByUserId: $input->changedByUserId,
            notes: $input->notes,
            changedAt: new DateTimeImmutable('now'),
        ));

        return Result::success(ChangeOperationStatusOutput::fromOperation($updatedOperation));
    }
}
