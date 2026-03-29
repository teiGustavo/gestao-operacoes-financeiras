<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Operation\Contracts\Repositories\OperationStatusHistoryRepositoryInterface;
use App\Domain\Operation\Entities\OperationStatusHistory;
use App\Infrastructure\Data\Operation\OperationStatusHistoryData;
use App\Infrastructure\Exceptions\InfrastructureUnavailableException;
use App\Infrastructure\Repositories\Mappers\Operation\OperationStatusHistoryDataMapper;
use App\Models\OperationStatusHistory as OperationStatusHistoryModel;
use Illuminate\Database\QueryException;
use Throwable;

final readonly class OperationStatusHistoryEloquentRepository implements OperationStatusHistoryRepositoryInterface
{
    public function __construct(
        private OperationStatusHistoryModel $operationStatusHistoryModel,
        private OperationStatusHistoryDataMapper $operationStatusHistoryDataMapper,
    ) {}

    public function append(OperationStatusHistory $history): void
    {
        try {
            $historyData = $this->operationStatusHistoryDataMapper->toPersistence($history);

            $this->operationStatusHistoryModel->newQuery()->create([
                'operation_id' => $historyData->operationId,
                'previous_status' => $historyData->previousStatus,
                'new_status' => $historyData->newStatus,
                'changed_by_user_id' => $historyData->changedByUserId,
                'notes' => $historyData->notes,
                'changed_at' => $historyData->changedAt,
            ]);
        } catch (Throwable $throwable) {
            if ($throwable instanceof InfrastructureUnavailableException) {
                throw $throwable;
            }

            throw new InfrastructureUnavailableException('Could not append operation_status_history.', 0, $throwable);
        }
    }

    /**
     * @return list<OperationStatusHistory>
     */
    public function listByOperationId(int $operationId): array
    {
        try {
            $models = $this->operationStatusHistoryModel->newQuery()
                ->where('operation_id', $operationId)
                ->orderBy('changed_at')
                ->get();

            return $models->map(function (OperationStatusHistoryModel $model): OperationStatusHistory {
                $historyData = new OperationStatusHistoryData(
                    id: $model->id,
                    operationId: (int) $model->operation_id,
                    previousStatus: $model->previous_status?->value,
                    newStatus: $model->new_status->value,
                    changedByUserId: (int) $model->changed_by_user_id,
                    notes: $model->notes,
                    changedAt: $model->changed_at->format('Y-m-d H:i:s'),
                );

                return $this->operationStatusHistoryDataMapper->toDomain($historyData);
            })->all();
        } catch (QueryException $queryException) {
            throw new InfrastructureUnavailableException('Could not retrieve all operations_status_history by operation_id.', 0, $queryException);
        }
    }
}
