<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Operation\Contracts\Repositories\OperationStatusHistoryRepositoryInterface;
use App\Domain\Operation\Entities\OperationStatusHistory;

final class InMemoryOperationStatusHistoryRepository implements OperationStatusHistoryRepositoryInterface
{
    /**
     * @var list<OperationStatusHistory>
     */
    private array $historyEntries = [];

    public function append(OperationStatusHistory $history): void
    {
        $this->historyEntries[] = $history;
    }

    /**
     * @return list<OperationStatusHistory>
     */
    public function listByOperationId(int $operationId): array
    {
        return array_values(array_filter(
            $this->historyEntries,
            fn (OperationStatusHistory $history) => $history->operationId === $operationId,
        ));
    }
}

