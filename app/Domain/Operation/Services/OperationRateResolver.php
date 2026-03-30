<?php

declare(strict_types=1);

namespace App\Domain\Operation\Services;

final class OperationRateResolver
{
    public function resolveFromTotalInterest(float $requestedValue, float $totalInterest): float
    {
        if ($requestedValue <= 0) {
            return 0.0;
        }

        return max(0.0, $totalInterest / $requestedValue);
    }
}
