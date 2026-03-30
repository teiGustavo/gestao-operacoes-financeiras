<?php

declare(strict_types=1);

use App\Domain\Operation\Services\PresentValueCalculator;

it('returns installment value when due date matches reference date', function () {
    $calculator = new PresentValueCalculator;

    $presentValue = $calculator->calculate(
        installmentValue: 100.0,
        dueDate: new DateTimeImmutable('2026-06-30'),
        referenceDate: new DateTimeImmutable('2026-06-30'),
        lateFeeRate: 0.02,
        lateInterestRate: 0.01,
        operationRate: 0.10,
    );

    expect($presentValue)->toBe(100.0);
});

it('calculates overdue installments using fee, daily late rate, and operation rate adjustment', function () {
    $calculator = new PresentValueCalculator;

    $presentValue = $calculator->calculate(
        installmentValue: 100.0,
        dueDate: new DateTimeImmutable('2026-06-20'),
        referenceDate: new DateTimeImmutable('2026-06-30'),
        lateFeeRate: 0.02,
        lateInterestRate: 0.01,
        operationRate: 0.10,
    );

    expect($presentValue)->toBe(115.23);
});

it('calculates future installments using discounted value', function () {
    $calculator = new PresentValueCalculator;

    $presentValue = $calculator->calculate(
        installmentValue: 100.0,
        dueDate: new DateTimeImmutable('2026-07-10'),
        referenceDate: new DateTimeImmutable('2026-06-30'),
        lateFeeRate: 0.02,
        lateInterestRate: 0.01,
        operationRate: 0.10,
    );

    expect($presentValue)->toBe(96.87);
});

it('applies banker rounding on .5 boundaries', function () {
    $calculator = new PresentValueCalculator;

    $presentValue = $calculator->calculate(
        installmentValue: 10.005,
        dueDate: new DateTimeImmutable('2026-06-30'),
        referenceDate: new DateTimeImmutable('2026-06-30'),
        lateFeeRate: 0.0,
        lateInterestRate: 0.0,
        operationRate: 0.0,
    );

    expect($presentValue)->toBe(10.0);
});

it('keeps value stable for overdue installment when all rates are zero', function () {
    $calculator = new PresentValueCalculator;

    $presentValue = $calculator->calculate(
        installmentValue: 99.99,
        dueDate: new DateTimeImmutable('2026-06-01'),
        referenceDate: new DateTimeImmutable('2026-06-15'),
        lateFeeRate: 0.0,
        lateInterestRate: 0.0,
        operationRate: 0.0,
    );

    expect($presentValue)->toBe(99.99);
});
