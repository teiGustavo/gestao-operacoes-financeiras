<?php

declare(strict_types=1);

namespace App\Infrastructure\Queries\Operation;

use App\Models\OperationReportRun;
use Illuminate\Support\Collection;

final class OperationReportRunLatestQuery
{
    /**
     * @return Collection<int, OperationReportRun>
     */
    public function latestForUser(int $userId, int $limit = 5): Collection
    {
        return OperationReportRun::query()
            ->where('requested_by_user_id', $userId)
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
