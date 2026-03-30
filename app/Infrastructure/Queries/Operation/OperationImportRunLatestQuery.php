<?php

declare(strict_types=1);

namespace App\Infrastructure\Queries\Operation;

use App\Models\OperationImportRun;
use Illuminate\Support\Collection;

final class OperationImportRunLatestQuery
{
    /**
     * @return Collection<int, OperationImportRun>
     */
    public function latestForUser(int $userId, int $limit = 5): Collection
    {
        return OperationImportRun::query()
            ->where('requested_by_user_id', $userId)
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
