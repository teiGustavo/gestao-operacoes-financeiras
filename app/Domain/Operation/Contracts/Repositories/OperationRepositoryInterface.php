<?php

declare(strict_types=1);

namespace App\Domain\Operation\Contracts\Repositories;

use App\Domain\Operation\Entities\Operation;

interface OperationRepositoryInterface
{
    public function findById(int $operationId): ?Operation;

    public function save(Operation $operation): Operation;
}
