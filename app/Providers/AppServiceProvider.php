<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Import\Contracts\OperationImportDataExtractorInterface;
use App\Infrastructure\Import\Contracts\OperationImportRowPersisterInterface;
use App\Infrastructure\Import\Extractors\CsvOperationImportDataExtractor;
use App\Infrastructure\Import\Persistence\OperationImportRowPersister;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OperationImportDataExtractorInterface::class, CsvOperationImportDataExtractor::class);
        $this->app->bind(OperationImportRowPersisterInterface::class, OperationImportRowPersister::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
