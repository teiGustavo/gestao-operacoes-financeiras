<?php

declare(strict_types=1);

namespace App\Infrastructure\Support;

use App\Application\Shared\Contracts\TransactionManagerInterface;
use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DatabaseTransactionManager implements TransactionManagerInterface
{
    /**
     * @throws Throwable
     */
    public function run(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }
}
