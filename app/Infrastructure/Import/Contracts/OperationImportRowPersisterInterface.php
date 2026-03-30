<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Contracts;

interface OperationImportRowPersisterInterface
{
    /**
     * @param  array<string, string>  $row
     */
    public function persist(array $row): void;

    /**
     * @param  array<int, array<string, string>>  $collection
     * @return array{upsert_clients:float,upsert_agreements:float,load_client_ids:float,insert_operations:float,insert_installments:float,total:float}
     */
    public function persistMany(array $collection): array;
}
