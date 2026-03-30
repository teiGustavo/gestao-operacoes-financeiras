<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Persistence;

use App\Infrastructure\Queries\Eloquent\MySQL\OperationImportPersistenceEloquentMysqlQuery;
use InvalidArgumentException;

final readonly class InstallmentsBatchWriter
{
    public function __construct(
        private OperationImportPersistenceEloquentMysqlQuery $operationImportPersistenceQuery,
        private OperationImportPayloadBuilder $operationImportPayloadBuilder,
        private OperationIdsByInstallmentsCountGrouper $operationIdsByInstallmentsCountGrouper,
    ) {}

    /**
     * @param  list<array<string, string>>  $sourceRowsChunk
     * @param  list<int>  $operationIds
     * @param  list<array<string, mixed>>  $installmentsBuffer
     */
    public function appendFromChunk(
        array $sourceRowsChunk,
        array $operationIds,
        array &$installmentsBuffer,
        int $rowsPerInstallmentsInsert,
        string $timestamp,
        string $paidAtFallback,
    ): void {
        if (count($sourceRowsChunk) !== count($operationIds)) {
            throw new InvalidArgumentException('quantidade de ids de operacoes divergente da quantidade de linhas processadas');
        }

        if ($operationIds !== [] && $this->operationImportPersistenceQuery->isMySqlDriver()) {
            $operationIdsByInstallmentsCount = $this->operationIdsByInstallmentsCountGrouper->group(
                sourceRowsChunk: $sourceRowsChunk,
                operationIds: $operationIds,
            );

            foreach ($operationIdsByInstallmentsCount as $installmentsCount => $groupedOperationIds) {
                $this->operationImportPersistenceQuery->insertInstallmentsFromOperationsMySql(
                    operationIds: $groupedOperationIds,
                    installmentsCount: $installmentsCount,
                    timestamp: $timestamp,
                    paidAtFallback: $paidAtFallback,
                );
            }

            return;
        }

        foreach ($sourceRowsChunk as $index => $row) {
            $operationId = $operationIds[$index];

            foreach ($this->operationImportPayloadBuilder->buildInstallmentsPayload(
                row: $row,
                operationId: $operationId,
                timestamp: $timestamp,
                paidAtFallback: $paidAtFallback,
            ) as $installmentPayload) {
                $installmentsBuffer[] = $installmentPayload;

                if (count($installmentsBuffer) >= $rowsPerInstallmentsInsert) {
                    $this->flush($installmentsBuffer);
                }
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $installmentsBuffer
     */
    public function flush(array &$installmentsBuffer): void
    {
        if ($installmentsBuffer === []) {
            return;
        }

        $this->operationImportPersistenceQuery->insertInstallments($installmentsBuffer);
        $installmentsBuffer = [];
    }
}
