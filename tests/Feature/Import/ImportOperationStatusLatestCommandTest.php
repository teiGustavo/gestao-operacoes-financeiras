<?php

declare(strict_types=1);

use App\Models\OperationImportRun;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows latest import run status details', function () {
    OperationImportRun::query()->create([
        'file_path' => '/tmp/older.csv',
        'status' => OperationImportRun::STATUS_COMPLETED,
        'total_rows' => 1,
        'imported_rows' => 1,
        'rejected_rows' => 0,
    ]);

    $latestRun = OperationImportRun::query()->create([
        'file_path' => '/tmp/latest.csv',
        'status' => OperationImportRun::STATUS_COMPLETED_WITH_ERRORS,
        'queued_at' => now()->subMinute(),
        'started_at' => now()->subSeconds(50),
        'finished_at' => now(),
        'total_rows' => 10,
        'imported_rows' => 8,
        'rejected_rows' => 2,
        'metrics' => ['total' => 1.2345],
    ]);

    $this->artisan('operations:import:status-latest')
        ->expectsOutputToContain('Status da importacao:')
        ->expectsOutputToContain('- run_id: '.$latestRun->id)
        ->expectsOutputToContain('- status: '.OperationImportRun::STATUS_COMPLETED_WITH_ERRORS)
        ->expectsOutputToContain('- total_rows: 10')
        ->expectsOutputToContain('- imported_rows: 8')
        ->expectsOutputToContain('- rejected_rows: 2')
        ->expectsOutputToContain('- total_seconds: 1.2345')
        ->assertSuccessful();
});

it('fails when there is no import run to show latest status', function () {
    $this->artisan('operations:import:status-latest')
        ->expectsOutputToContain('nenhuma importacao encontrada')
        ->assertFailed();
});

it('shows fallback error code for latest failed import run', function () {
    OperationImportRun::query()->create([
        'file_path' => '/tmp/older.csv',
        'status' => OperationImportRun::STATUS_COMPLETED,
    ]);

    OperationImportRun::query()->create([
        'file_path' => '/tmp/latest-failed.csv',
        'status' => OperationImportRun::STATUS_FAILED,
        'queued_at' => now()->subMinute(),
        'started_at' => now()->subSeconds(50),
        'finished_at' => now(),
        'failure_message' => 'falha simulada latest',
    ]);

    $this->artisan('operations:import:status-latest')
        ->expectsOutputToContain('- status: '.OperationImportRun::STATUS_FAILED)
        ->expectsOutputToContain('- failure_message: falha simulada latest')
        ->expectsOutputToContain('- error_code: '.OperationImportRun::ERROR_CODE_UNEXPECTED)
        ->assertSuccessful();
});
