<?php

declare(strict_types=1);

namespace App\Domain\Operation\Contracts\Repositories;

interface OperationStatusHistoryRepositoryInterface
{
    /**
     * @param array{
     *     operation_id: int,
     *     previous_status?: string|null,
     *     new_status: string,
     *     changed_by_user_id: int,
     *     notes?: string|null,
     *     changed_at?: string|null
     * } $historyPayload
     */
    public function append(array $historyPayload): void;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByOperationId(int $operationId): array;
}

