<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Client\Contracts\Repositories\ClientRepositoryInterface;
use App\Domain\Operation\Contracts\Repositories\OperationRepositoryInterface;
use App\Domain\Operation\Contracts\Repositories\OperationStatusHistoryRepositoryInterface;
use App\Domain\User\Contracts\Repositories\UserRepositoryInterface;
use App\Infrastructure\Repositories\Eloquent\ClientEloquentRepository;
use App\Infrastructure\Repositories\Eloquent\OperationEloquentRepository;
use App\Infrastructure\Repositories\Eloquent\OperationStatusHistoryEloquentRepository;
use App\Infrastructure\Repositories\Eloquent\UserEloquentRepository;
use Illuminate\Support\ServiceProvider;

class DomainInfrastructureServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(ClientRepositoryInterface::class, ClientEloquentRepository::class);
        $this->app->bind(OperationRepositoryInterface::class, OperationEloquentRepository::class);
        $this->app->bind(OperationStatusHistoryRepositoryInterface::class, OperationStatusHistoryEloquentRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserEloquentRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void {}
}
