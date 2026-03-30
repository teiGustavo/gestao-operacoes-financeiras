<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Normalizers;

use DateTimeImmutable;

final class DateValueNormalizer
{
    public function normalizeToYmd(string $value): ?string
    {
        $date = $this->normalizeToDateTime($value);

        return $date?->format('Y-m-d');
    }

    public function normalizeToDateTime(string $value): ?DateTimeImmutable
    {
        $rawValue = trim($value);

        $candidateFormats = str_contains($rawValue, '/')
            ? ['j/n/Y', 'd/m/Y']
            : ['Y-n-j', 'Y-m-d'];

        foreach ($candidateFormats as $candidateFormat) {
            $date = DateTimeImmutable::createFromFormat($candidateFormat, $rawValue);

            if ($date instanceof DateTimeImmutable && $date->format($candidateFormat) === $rawValue) {
                return $date;
            }
        }

        return null;
    }
}
