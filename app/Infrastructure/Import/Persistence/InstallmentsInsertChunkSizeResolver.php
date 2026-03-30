<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Persistence;

final class InstallmentsInsertChunkSizeResolver
{
    private const int INSTALLMENTS_COLUMNS_COUNT = 9;

    private const int MAX_INSERT_PLACEHOLDERS = 60_000;

    private const int INSTALLMENTS_INSERT_TARGET_CHUNK_SIZE = 2_000;

    public function resolve(): int
    {
        $rowsByPlaceholderBudget = intdiv(self::MAX_INSERT_PLACEHOLDERS, self::INSTALLMENTS_COLUMNS_COUNT);

        return max(1, min(self::INSTALLMENTS_INSERT_TARGET_CHUNK_SIZE, $rowsByPlaceholderBudget));
    }
}
