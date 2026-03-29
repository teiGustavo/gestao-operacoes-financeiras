<?php

declare(strict_types=1);

namespace App\Domain\Operation\Contracts\Repositories;

use App\Domain\Operation\Entities\OperationStatusHistory;

interface OperationStatusHistoryRepositoryInterface
{
    public function append(OperationStatusHistory $history): void;

    /**
     * @return list<OperationStatusHistory>
     */
    public function listByOperationId(int $operationId): array;
}
