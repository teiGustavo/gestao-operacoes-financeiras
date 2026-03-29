<?php

declare(strict_types=1);

use App\Domain\Operation\Entities\Installment;

it('creates installment entity with expected values', function () {
    $installment = new Installment(
        id: 1,
        operationId: 10,
        installmentNumber: 3,
        dueDate: new DateTimeImmutable('2026-08-01'),
        value: 199.90,
        paid: true,
        paidAt: new DateTimeImmutable('2026-08-03 10:15:00'),
        paidByUserId: 7,
    );

    expect($installment->id)->toBe(1)
        ->and($installment->operationId)->toBe(10)
        ->and($installment->installmentNumber)->toBe(3)
        ->and($installment->dueDate->format('Y-m-d'))->toBe('2026-08-01')
        ->and($installment->value)->toBe(199.90)
        ->and($installment->paid)->toBeTrue()
        ->and($installment->paidAt?->format('Y-m-d H:i:s'))->toBe('2026-08-03 10:15:00')
        ->and($installment->paidByUserId)->toBe(7);
});

