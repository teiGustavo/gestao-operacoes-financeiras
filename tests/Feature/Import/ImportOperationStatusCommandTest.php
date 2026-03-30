<?php

declare(strict_types=1);

use App\Models\OperationImportRun;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows import run status details when run exists', function () {
    $run = OperationImportRun::query()->create([
        'file_path' => '/tmp/sample.csv',
        'status' => OperationImportRun::STATUS_COMPLETED_WITH_ERRORS,
        'queued_at' => now()->subMinute(),
        'started_at' => now()->subSeconds(50),
        'finished_at' => now(),
        'total_rows' => 10,
        'imported_rows' => 8,
        'rejected_rows' => 2,
        'error_summary' => ['produto: Mapeamento invalido na linha 4' => 2],
        'metrics' => ['total' => 1.2345],
    ]);

    $this->artisan('operations:import:status', ['run_id' => $run->id])
        ->expectsOutputToContain('Status da importacao:')
        ->expectsOutputToContain('- run_id: '.$run->id)
        ->expectsOutputToContain('- status: '.OperationImportRun::STATUS_COMPLETED_WITH_ERRORS)
        ->expectsOutputToContain('- total_rows: 10')
        ->expectsOutputToContain('- imported_rows: 8')
        ->expectsOutputToContain('- rejected_rows: 2')
        ->expectsOutputToContain('- total_seconds: 1.2345')
        ->assertSuccessful();
});

it('fails when import run does not exist', function () {
    $this->artisan('operations:import:status', ['run_id' => 999_999])
        ->expectsOutputToContain('nao encontrado')
        ->assertFailed();
});

it('shows fallback error code when failed import run has no stored code', function () {
    $run = OperationImportRun::query()->create([
        'file_path' => '/tmp/failed.csv',
        'status' => OperationImportRun::STATUS_FAILED,
        'queued_at' => now()->subMinute(),
        'started_at' => now()->subSeconds(50),
        'finished_at' => now(),
        'failure_message' => 'falha simulada',
    ]);

    $this->artisan('operations:import:status', ['run_id' => $run->id])
        ->expectsOutputToContain('- status: '.OperationImportRun::STATUS_FAILED)
        ->expectsOutputToContain('- failure_message: falha simulada')
        ->expectsOutputToContain('- error_code: '.OperationImportRun::ERROR_CODE_UNEXPECTED)
        ->assertSuccessful();
});
