<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Persistence;

use App\Infrastructure\Import\Contracts\OperationImportRowPersisterInterface;
use App\Infrastructure\Queries\Eloquent\MySQL\OperationImportPersistenceEloquentMysqlQuery;
use DateTimeImmutable;
use InvalidArgumentException;

final class OperationImportRowPersister implements OperationImportRowPersisterInterface
{
    private const int OPERATIONS_INSERT_CHUNK_SIZE = 2_000;

    public function __construct(
        private readonly OperationImportPersistenceEloquentMysqlQuery $operationImportPersistenceQuery,
        private readonly OperationImportPayloadBuilder $operationImportPayloadBuilder,
        private readonly InstallmentsBatchWriter $installmentsBatchWriter,
        private readonly InstallmentsInsertChunkSizeResolver $installmentsInsertChunkSizeResolver,
    ) {}

    public function persistMany(array $collection): array
    {
        $persisterStart = microtime(true);
        $upsertClientsElapsed = 0.0;
        $upsertAgreementsElapsed = 0.0;
        $loadClientIdsElapsed = 0.0;
        $insertOperationsElapsed = 0.0;
        $insertInstallmentsElapsed = 0.0;

        if ($collection === []) {
            return [
                'upsert_clients' => 0.0,
                'upsert_agreements' => 0.0,
                'load_client_ids' => 0.0,
                'insert_operations' => 0.0,
                'insert_installments' => 0.0,
                'total' => 0.0,
            ];
        }

        $timestamp = (new DateTimeImmutable)->format('Y-m-d H:i:s');

        // 1) upsert de Client e Agreement em lote
        $upsertClientsStart = microtime(true);
        $this->operationImportPersistenceQuery->upsertClients($collection, $timestamp);
        $upsertClientsElapsed += microtime(true) - $upsertClientsStart;

        $upsertAgreementsStart = microtime(true);
        $agreementIdMap = $this->operationImportPersistenceQuery->upsertAndResolveAgreementIds($collection, $timestamp);
        $upsertAgreementsElapsed += microtime(true) - $upsertAgreementsStart;

        // 2) carregar mapas de IDs
        $loadClientIdsStart = microtime(true);
        $clientIdByCpf = $this->operationImportPersistenceQuery->loadClientIdByCpf($collection);
        $loadClientIdsElapsed += microtime(true) - $loadClientIdsStart;
        $paidAtFallback = $timestamp;
        $clientIdByEmail = null;

        // 3) inserir operações em lote e acumular parcelas para insert em chunks seguros
        $rowsPerInstallmentsInsert = $this->installmentsInsertChunkSizeResolver->resolve();
        $operationPayloadChunk = [];
        $sourceRowsChunk = [];
        $installmentsBuffer = [];

        $processOperationChunk = function () use (
            &$operationPayloadChunk,
            &$sourceRowsChunk,
            &$installmentsBuffer,
            $rowsPerInstallmentsInsert,
            $timestamp,
            $paidAtFallback,
            &$insertOperationsElapsed,
            &$insertInstallmentsElapsed,
        ): void {
            $this->processOperationChunk(
                operationPayloadChunk: $operationPayloadChunk,
                sourceRowsChunk: $sourceRowsChunk,
                installmentsBuffer: $installmentsBuffer,
                rowsPerInstallmentsInsert: $rowsPerInstallmentsInsert,
                timestamp: $timestamp,
                paidAtFallback: $paidAtFallback,
                insertOperationsElapsed: $insertOperationsElapsed,
                insertInstallmentsElapsed: $insertInstallmentsElapsed,
            );
        };

        foreach ($collection as $row) {
            try {
                $operationPayloadChunk[] = $this->buildOperationPayload(
                    row: $row,
                    clientIdByCpf: $clientIdByCpf,
                    clientIdByEmail: [],
                    agreementIdMap: $agreementIdMap,
                    timestamp: $timestamp,
                );
            } catch (InvalidArgumentException $invalidArgumentException) {
                if ($invalidArgumentException->getMessage() !== 'cliente nao encontrado apos upsert') {
                    throw $invalidArgumentException;
                }

                if ($clientIdByEmail === null) {
                    $loadClientIdsFallbackStart = microtime(true);
                    $clientIdByEmail = $this->operationImportPersistenceQuery->loadClientIdByEmail($collection);
                    $loadClientIdsElapsed += microtime(true) - $loadClientIdsFallbackStart;
                }

                $operationPayloadChunk[] = $this->buildOperationPayload(
                    row: $row,
                    clientIdByCpf: $clientIdByCpf,
                    clientIdByEmail: $clientIdByEmail,
                    agreementIdMap: $agreementIdMap,
                    timestamp: $timestamp,
                );
            }

            $sourceRowsChunk[] = $row;

            if (count($operationPayloadChunk) >= self::OPERATIONS_INSERT_CHUNK_SIZE) {
                $processOperationChunk();
            }
        }

        $processOperationChunk();

        if ($installmentsBuffer !== []) {
            $insertInstallmentsStart = microtime(true);
            $this->installmentsBatchWriter->flush($installmentsBuffer);
            $insertInstallmentsElapsed += microtime(true) - $insertInstallmentsStart;
        }

        return [
            'upsert_clients' => $upsertClientsElapsed,
            'upsert_agreements' => $upsertAgreementsElapsed,
            'load_client_ids' => $loadClientIdsElapsed,
            'insert_operations' => $insertOperationsElapsed,
            'insert_installments' => $insertInstallmentsElapsed,
            'total' => microtime(true) - $persisterStart,
        ];
    }

    /**
     * @param  array<string, string>  $row
     */
    public function persist(array $row): void
    {
        $this->persistMany([$row]);
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, int>  $clientIdByCpf
     * @param  array<string, int>  $clientIdByEmail
     * @param  array<string, int>  $agreementIdMap
     * @return array<string, mixed>
     */
    private function buildOperationPayload(
        array $row,
        array $clientIdByCpf,
        array $clientIdByEmail,
        array $agreementIdMap,
        string $timestamp,
    ): array {
        return $this->operationImportPayloadBuilder->buildOperationPayload(
            row: $row,
            clientIdByCpf: $clientIdByCpf,
            clientIdByEmail: $clientIdByEmail,
            agreementIdMap: $agreementIdMap,
            timestamp: $timestamp,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $operationPayloadChunk
     * @param  list<array<string, string>>  $sourceRowsChunk
     * @param  list<array<string, mixed>>  $installmentsBuffer
     */
    private function processOperationChunk(
        array &$operationPayloadChunk,
        array &$sourceRowsChunk,
        array &$installmentsBuffer,
        int $rowsPerInstallmentsInsert,
        string $timestamp,
        string $paidAtFallback,
        float &$insertOperationsElapsed,
        float &$insertInstallmentsElapsed,
    ): void {
        if ($operationPayloadChunk === []) {
            return;
        }

        $insertOperationsStart = microtime(true);
        $operationIds = $this->operationImportPersistenceQuery->insertOperationsAndResolveIds($operationPayloadChunk);
        $insertOperationsElapsed += microtime(true) - $insertOperationsStart;

        $insertInstallmentsStart = microtime(true);
        $this->installmentsBatchWriter->appendFromChunk(
            sourceRowsChunk: $sourceRowsChunk,
            operationIds: $operationIds,
            installmentsBuffer: $installmentsBuffer,
            rowsPerInstallmentsInsert: $rowsPerInstallmentsInsert,
            timestamp: $timestamp,
            paidAtFallback: $paidAtFallback,
        );
        $insertInstallmentsElapsed += microtime(true) - $insertInstallmentsStart;

        $operationPayloadChunk = [];
        $sourceRowsChunk = [];
    }
}
