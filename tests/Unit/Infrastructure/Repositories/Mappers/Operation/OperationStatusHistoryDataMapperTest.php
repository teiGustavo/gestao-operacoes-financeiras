<?php

declare(strict_types=1);

use App\Domain\Operation\Entities\OperationStatusHistory;
use App\Domain\Operation\OperationStatus;
use App\Infrastructure\Data\Operation\OperationStatusHistoryData;
use App\Infrastructure\Repositories\Mappers\Operation\OperationStatusHistoryDataMapper;

it('maps operation status history domain entity to persistence data and back', function () {
    $mapper = new OperationStatusHistoryDataMapper();

    $history = new OperationStatusHistory(
        id: 9,
        operationId: 21,
        previousStatus: OperationStatus::APPROVED,
        newStatus: OperationStatus::DISBURSED,
        changedByUserId: 4,
        notes: 'Mudanca de status',
        changedAt: new DateTimeImmutable('2026-05-02 09:30:00'),
    );

    $persistenceData = $mapper->toPersistence($history);

    expect($persistenceData)->toBeInstanceOf(OperationStatusHistoryData::class)
        ->and($persistenceData->previousStatus)->toBe(OperationStatus::APPROVED->value)
        ->and($persistenceData->newStatus)->toBe(OperationStatus::DISBURSED->value);

    $mappedHistory = $mapper->toDomain($persistenceData);

    expect($mappedHistory)->toBeInstanceOf(OperationStatusHistory::class)
        ->and($mappedHistory->changedByUserId)->toBe(4)
        ->and($mappedHistory->changedAt->format('Y-m-d H:i:s'))->toBe('2026-05-02 09:30:00');
});

