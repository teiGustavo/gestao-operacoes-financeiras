<?php

declare(strict_types=1);

namespace App\Infrastructure\Queries\Eloquent\MySQL;

use App\Models\Agreement;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class OperationImportPersistenceEloquentMysqlQuery
{
    private const string AUTO_GENERATED_AGREEMENT_NAME = 'Conveniada Gerada Automaticamente';

    /**
     * @param  array<int, array<string, string>>  $collection
     */
    public function upsertClients(array $collection, string $timestamp): void
    {
        $clientsByCpf = [];

        foreach ($collection as $row) {
            $clientsByCpf[$row['cpf']] = [
                'cpf' => $row['cpf'],
                'name' => $row['nome'],
                'birth_date' => $row['dt_nasc'],
                'gender' => $row['sexo'],
                'email' => $row['email'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        Client::query()->upsert(
            array_values($clientsByCpf),
            ['cpf'],
            ['name', 'birth_date', 'gender', 'email', 'updated_at'],
        );
    }

    /**
     * @param  array<int, array<string, string>>  $collection
     * @return array<string,int>
     */
    public function upsertAndResolveAgreementIds(array $collection, string $timestamp): array
    {
        $agreementRows = [];

        foreach ($collection as $row) {
            if ($row['conveniada_id'] === '') {
                continue;
            }

            $agreementRows[$row['conveniada_id']] = [
                'id' => (int) $row['conveniada_id'],
                'name' => 'Conveniada '.$row['conveniada_id'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        if ($agreementRows !== []) {
            Agreement::query()->upsert(
                array_values($agreementRows),
                ['id'],
                ['name', 'updated_at'],
            );
        }

        /** @var Agreement $generatedAgreement */
        $generatedAgreement = Agreement::query()->firstOrCreate(
            ['name' => self::AUTO_GENERATED_AGREEMENT_NAME],
            ['created_at' => $timestamp, 'updated_at' => $timestamp],
        );

        $agreementIdMap = ['' => (int) $generatedAgreement->id];

        foreach (array_keys($agreementRows) as $agreementId) {
            $agreementIdMap[(string) $agreementId] = (int) $agreementId;
        }

        return $agreementIdMap;
    }

    /**
     * @param  array<int, array<string, string>>  $collection
     * @return array<string,int>
     */
    public function loadClientIdByCpf(array $collection): array
    {
        $cpfs = array_map(static fn (array $row): string => $row['cpf'], $collection)
                |> array_unique(...)
                |> array_values(...);

        return Client::query()
            ->whereIn('cpf', $cpfs)
            ->pluck('id', 'cpf')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, array<string, string>>  $collection
     * @return array<string,int>
     */
    public function loadClientIdByEmail(array $collection): array
    {
        $emails = array_map(static fn (array $row): string => $row['email'], $collection)
                |> array_unique(...)
                |> array_values(...);

        return Client::query()
            ->whereIn('email', $emails)
            ->pluck('id', 'email')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $operationPayloadChunk
     * @return list<int>
     */
    public function insertOperationsAndResolveIds(array $operationPayloadChunk): array
    {
        DB::table('operations')->insert($operationPayloadChunk);

        return $this->resolveInsertedOperationIds(count($operationPayloadChunk));
    }

    /**
     * @return list<int>
     */
    public function resolveInsertedOperationIds(int $insertedRowsCount): array
    {
        $lastInsertId = (int) DB::getPdo()->lastInsertId();

        if ($lastInsertId <= 0) {
            throw new InvalidArgumentException('nao foi possivel resolver os ids das operacoes inseridas');
        }

        $firstInsertedId = match (DB::getDriverName()) {
            'mysql', 'mariadb' => $lastInsertId,
            default => $lastInsertId - $insertedRowsCount + 1,
        };

        if ($firstInsertedId <= 0) {
            throw new InvalidArgumentException('nao foi possivel resolver o intervalo de ids das operacoes inseridas');
        }

        return range($firstInsertedId, $firstInsertedId + $insertedRowsCount - 1);
    }

    /**
     * @param  list<array<string, mixed>>  $installmentsBuffer
     */
    public function insertInstallments(array $installmentsBuffer): void
    {
        DB::table('installments')->insert($installmentsBuffer);
    }

    /**
     * @param  list<int>  $operationIds
     */
    public function insertInstallmentsFromOperationsMySql(
        array $operationIds,
        int $installmentsCount,
        string $timestamp,
        string $paidAtFallback,
    ): void {
        $inClausePlaceholders = $operationIds
                |> count(...)
                |> (fn ($count) => array_fill(0, $count, '?'))
                |> (fn ($placeholders) => implode(',', $placeholders));

        $sql = <<<'SQL'
            INSERT INTO installments (
                operation_id,
                installment_number,
                due_date,
                value,
                paid,
                paid_at,
                paid_by_user_id,
                created_at,
                updated_at
            )
            WITH RECURSIVE selected_operations AS (
                SELECT
                    id,
                    first_due_date,
                    installment_value,
                    paid_installments_count,
                    payment_date
                FROM operations
                WHERE id IN (%s)
            ),
            seq AS (
                SELECT 1 AS installment_number
                UNION ALL
                SELECT installment_number + 1
                FROM seq
                WHERE installment_number < ?
            )
            SELECT
                selected_operations.id,
                seq.installment_number,
                DATE_ADD(selected_operations.first_due_date, INTERVAL (seq.installment_number - 1) MONTH),
                selected_operations.installment_value,
                seq.installment_number <= selected_operations.paid_installments_count,
                IF(seq.installment_number <= selected_operations.paid_installments_count, COALESCE(selected_operations.payment_date, ?), NULL),
                NULL,
                ?,
                ?
            FROM selected_operations
            CROSS JOIN seq
        SQL;

        $bindings = [
            ...$operationIds,
            $installmentsCount,
            $paidAtFallback,
            $timestamp,
            $timestamp,
        ];

        DB::insert(sprintf($sql, $inClausePlaceholders), $bindings);
    }

    public function isMySqlDriver(): bool
    {
        return DB::getDriverName() === 'mysql';
    }
}
