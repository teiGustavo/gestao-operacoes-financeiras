<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Normalizers;

final class DecimalValueNormalizer
{
    public function normalize(string $value): ?float
    {
        $normalizedValue = str_replace(',', '.', trim($value));

        if (! is_numeric($normalizedValue)) {
            return null;
        }

        return (float) $normalizedValue;
    }
}
