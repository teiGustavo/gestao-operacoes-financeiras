<?php

declare(strict_types=1);

use App\Domain\Operation\Entities\OperationStatusHistory;
use App\Domain\Operation\OperationStatus;

it('creates operation status history entity with expected values', function () {
    $history = new OperationStatusHistory(
        id: 9,
        operationId: 101,
        previousStatus: OperationStatus::UNDER_REVIEW,
        newStatus: OperationStatus::AWAITING_SIGNATURE,
        changedByUserId: 5,
        notes: 'Checklist documental aprovado',
        changedAt: new DateTimeImmutable('2026-05-01 11:30:00'),
    );

    expect($history->id)->toBe(9)
        ->and($history->operationId)->toBe(101)
        ->and($history->previousStatus)->toBe(OperationStatus::UNDER_REVIEW)
        ->and($history->newStatus)->toBe(OperationStatus::AWAITING_SIGNATURE)
        ->and($history->changedByUserId)->toBe(5)
        ->and($history->notes)->toBe('Checklist documental aprovado')
        ->and($history->changedAt->format('Y-m-d H:i:s'))->toBe('2026-05-01 11:30:00');
});
