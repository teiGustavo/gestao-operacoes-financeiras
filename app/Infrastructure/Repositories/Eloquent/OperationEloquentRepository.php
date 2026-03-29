<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Operation\Contracts\Repositories\OperationRepositoryInterface;
use App\Domain\Operation\Entities\Operation;
use App\Infrastructure\Data\Operation\OperationData;
use App\Infrastructure\Exceptions\InfrastructureUnavailableException;
use App\Infrastructure\Repositories\Mappers\Operation\OperationDataMapper;
use App\Models\Operation as OperationModel;
use Illuminate\Database\QueryException;
use Throwable;

final readonly class OperationEloquentRepository implements OperationRepositoryInterface
{
    public function __construct(
        private OperationModel $operationModel,
        private OperationDataMapper $operationDataMapper,
    ) {}

    public function findById(int $operationId): ?Operation
    {
        try {
            $model = $this->operationModel->newQuery()->find($operationId);

            if ($model === null) {
                return null;
            }

            return $this->mapModelToDomain($model);
        } catch (QueryException $queryException) {
            throw new InfrastructureUnavailableException('Could not retrieve operation by id.', 0, $queryException);
        }
    }

    public function save(Operation $operation): Operation
    {
        try {
            $operationData = $this->operationDataMapper->toPersistence($operation);

            $model = $operationData->id === null
                ? $this->operationModel->newInstance()
                : $this->operationModel->newQuery()->findOrFail($operationData->id);

            $model->fill([
                'client_id' => $operationData->clientId,
                'agreement_id' => $operationData->agreementId,
                'requested_value' => $operationData->requestedValue,
                'disbursement_value' => $operationData->disbursementValue,
                'total_interest' => $operationData->totalInterest,
                'late_fee_rate' => $operationData->lateFeeRate,
                'late_interest_rate' => $operationData->lateInterestRate,
                'installments_count' => $operationData->installmentsCount,
                'paid_installments_count' => $operationData->paidInstallmentsCount,
                'installment_value' => $operationData->installmentValue,
                'status' => $operationData->status,
                'product_type' => $operationData->productType,
                'first_due_date' => $operationData->firstDueDate,
                'proposal_created_date' => $operationData->proposalCreatedDate,
                'payment_date' => $operationData->paymentDate,
            ]);

            $model->save();

            return $this->mapModelToDomain($model);
        } catch (Throwable $throwable) {
            if ($throwable instanceof InfrastructureUnavailableException) {
                throw $throwable;
            }

            throw new InfrastructureUnavailableException('Could not save operation.', 0, $throwable);
        }
    }

    private function mapModelToDomain(OperationModel $operationModel): Operation
    {
        $operationData = new OperationData(
            id: $operationModel->id,
            clientId: (int) $operationModel->client_id,
            agreementId: (int) $operationModel->agreement_id,
            requestedValue: (float) $operationModel->requested_value,
            disbursementValue: (float) $operationModel->disbursement_value,
            totalInterest: (float) $operationModel->total_interest,
            lateFeeRate: (float) $operationModel->late_fee_rate,
            lateInterestRate: (float) $operationModel->late_interest_rate,
            installmentsCount: (int) $operationModel->installments_count,
            paidInstallmentsCount: (int) $operationModel->paid_installments_count,
            installmentValue: (float) $operationModel->installment_value,
            status: $operationModel->status->value,
            productType: $operationModel->product_type->value,
            firstDueDate: $operationModel->first_due_date->format('Y-m-d'),
            proposalCreatedDate: $operationModel->proposal_created_date->format('Y-m-d'),
            paymentDate: $operationModel->payment_date?->format('Y-m-d'),
        );

        return $this->operationDataMapper->toDomain($operationData);
    }
}
