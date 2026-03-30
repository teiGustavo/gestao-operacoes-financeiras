<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

use Closure;

interface TransactionManagerInterface
{
    public function run(Closure $callback): mixed;
}
