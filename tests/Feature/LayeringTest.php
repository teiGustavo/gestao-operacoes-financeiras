<?php

declare(strict_types=1);

use App\Providers\DomainInfrastructureServiceProvider;
use Illuminate\Support\Facades\File;

it('keeps domain isolated from infrastructure dependencies', function () {
    $domainFiles = File::allFiles(app_path('Domain'));

    foreach ($domainFiles as $domainFile) {
        $sourceCode = $domainFile->getContents();

        expect($sourceCode)->not->toContain('App\\Infrastructure\\')
            ->and($sourceCode)->not->toContain('App\\Models\\')
            ->and($sourceCode)->not->toContain('Illuminate\\Database\\')
            ->and($sourceCode)->not->toContain('Spatie\\LaravelData\\');
    }
});

it('keeps application use cases isolated from eloquent details', function () {
    $applicationPath = app_path('Application');

    if (! is_dir($applicationPath)) {
        expect(true)->toBeTrue();

        return;
    }

    $applicationFiles = File::allFiles($applicationPath);

    foreach ($applicationFiles as $applicationFile) {
        $sourceCode = $applicationFile->getContents();

        expect($sourceCode)->not->toContain('App\\Models\\')
            ->and($sourceCode)->not->toContain('Illuminate\\Database\\Eloquent\\');
    }
});

it('registers the domain infrastructure provider', function () {
    expect(app()->getProvider(DomainInfrastructureServiceProvider::class))->not->toBeNull();
});
