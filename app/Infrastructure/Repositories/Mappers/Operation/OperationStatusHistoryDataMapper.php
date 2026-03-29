<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Mappers\Operation;

use App\Domain\Operation\Entities\OperationStatusHistory;
use App\Domain\Operation\OperationStatus;
use App\Domain\Shared\Contracts\Mapper\DomainMapperInterface;
use App\Infrastructure\Data\Operation\OperationStatusHistoryData;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * @implements DomainMapperInterface<OperationStatusHistory, OperationStatusHistoryData>
 */
final class OperationStatusHistoryDataMapper implements DomainMapperInterface
{
    /**
     * @param  OperationStatusHistoryData  $payload
     *
     * @throws InvalidArgumentException
     */
    public function toDomain($payload): OperationStatusHistory
    {
        if (! $payload instanceof OperationStatusHistoryData) {
            throw new InvalidArgumentException('Expected OperationStatusHistoryData payload for domain mapping.');
        }

        return new OperationStatusHistory(
            id: $payload->id,
            operationId: $payload->operationId,
            previousStatus: $payload->previousStatus === null ? null : OperationStatus::from($payload->previousStatus),
            newStatus: OperationStatus::from($payload->newStatus),
            changedByUserId: $payload->changedByUserId,
            notes: $payload->notes,
            changedAt: new DateTimeImmutable($payload->changedAt),
        );
    }

    /**
     * @param  OperationStatusHistory  $payload
     *
     * @throws InvalidArgumentException
     */
    public function toPersistence($payload): OperationStatusHistoryData
    {
        if (! $payload instanceof OperationStatusHistory) {
            throw new InvalidArgumentException('Expected OperationStatusHistory payload for persistence mapping.');
        }

        return new OperationStatusHistoryData(
            id: $payload->id,
            operationId: $payload->operationId,
            previousStatus: $payload->previousStatus?->value,
            newStatus: $payload->newStatus->value,
            changedByUserId: $payload->changedByUserId,
            notes: $payload->notes,
            changedAt: $payload->changedAt->format('Y-m-d H:i:s'),
        );
    }
}
