<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Operation\Contracts\Repositories\OperationRepositoryInterface;
use App\Domain\Operation\Entities\Operation;

final class InMemoryOperationRepository implements OperationRepositoryInterface
{
    /**
     * @var array<int, Operation>
     */
    private array $operations = [];

    public function findById(int $operationId): ?Operation
    {
        return $this->operations[$operationId] ?? null;
    }

    public function save(Operation $operation): Operation
    {
        $id = $operation->id ?? (count($this->operations) + 1);
        $persistedOperation = $operation->withId($id);
        $this->operations[$id] = $persistedOperation;

        return $persistedOperation;
    }
}
