<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Persistence;

final class OperationIdsByInstallmentsCountGrouper
{
    /**
     * @param  list<array<string, string>>  $sourceRowsChunk
     * @param  list<int>  $operationIds
     * @return array<int, list<int>>
     */
    public function group(array $sourceRowsChunk, array $operationIds): array
    {
        $operationIdsByInstallmentsCount = [];

        foreach ($sourceRowsChunk as $index => $row) {
            $installmentsCount = (int) $row['quantidade_parcelas'];
            $operationIdsByInstallmentsCount[$installmentsCount] ??= [];
            $operationIdsByInstallmentsCount[$installmentsCount][] = $operationIds[$index];
        }

        return $operationIdsByInstallmentsCount;
    }
}
