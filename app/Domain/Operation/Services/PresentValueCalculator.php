<?php

declare(strict_types=1);

namespace App\Domain\Operation\Services;

use DateTimeInterface;

final class PresentValueCalculator
{
    public function calculate(
        float $installmentValue,
        DateTimeInterface $dueDate,
        DateTimeInterface $referenceDate,
        float $lateFeeRate,
        float $lateInterestRate,
        float $operationRate,
    ): float {
        $daysDifference = (int) $dueDate->diff($referenceDate)->format('%r%a');

        if ($daysDifference === 0) {
            return round($installmentValue, 2, \PHP_ROUND_HALF_EVEN);
        }

        if ($daysDifference > 0) {
            $overduePresentValue = $installmentValue * (1 + $lateFeeRate + ($lateInterestRate * $daysDifference));
            $interestAdjustment = $installmentValue * (((1 + $operationRate) ** ($daysDifference / 30)) - 1);

            return round($overduePresentValue + $interestAdjustment, 2, \PHP_ROUND_HALF_EVEN);
        }

        $advanceDays = abs($daysDifference);
        $discountFactor = (1 + $operationRate) ** ($advanceDays / 30);

        return round($installmentValue / $discountFactor, 2, \PHP_ROUND_HALF_EVEN);
    }
}
