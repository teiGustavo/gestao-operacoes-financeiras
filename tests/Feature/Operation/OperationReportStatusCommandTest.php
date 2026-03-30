<?php

declare(strict_types=1);

use App\Models\OperationReportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows operation report run status details by run id', function () {
    $user = User::factory()->create();

    $reportRun = OperationReportRun::query()->create([
        'status' => OperationReportRun::STATUS_COMPLETED,
        'requested_by_user_id' => $user->id,
        'reference_date' => '2026-06-30',
        'queued_at' => now()->subMinute(),
        'started_at' => now()->subSeconds(50),
        'finished_at' => now(),
        'total_rows' => 42,
        'output_file_path' => 'reports/operations-report-run-10.csv',
    ]);

    $this->artisan('operations:report:status', ['run_id' => (string) $reportRun->id])
        ->expectsOutputToContain('Status do relatorio:')
        ->expectsOutputToContain('- run_id: '.$reportRun->id)
        ->expectsOutputToContain('- status: '.OperationReportRun::STATUS_COMPLETED)
        ->expectsOutputToContain('- total_rows: 42')
        ->expectsOutputToContain('- output_file_path: reports/operations-report-run-10.csv')
        ->assertSuccessful();
});

it('fails when report run is not found by id', function () {
    $this->artisan('operations:report:status', ['run_id' => '999999'])
        ->expectsOutputToContain('report run 999999 nao encontrado')
        ->assertFailed();
});

it('shows fallback error code when failed report run has no stored code', function () {
    $user = User::factory()->create();

    $reportRun = OperationReportRun::query()->create([
        'status' => OperationReportRun::STATUS_FAILED,
        'requested_by_user_id' => $user->id,
        'reference_date' => '2026-06-30',
        'queued_at' => now()->subMinute(),
        'started_at' => now()->subSeconds(50),
        'finished_at' => now(),
        'total_rows' => 0,
        'output_file_path' => null,
        'failure_message' => 'falha simulada report',
    ]);

    $this->artisan('operations:report:status', ['run_id' => (string) $reportRun->id])
        ->expectsOutputToContain('- status: '.OperationReportRun::STATUS_FAILED)
        ->expectsOutputToContain('- failure_message: falha simulada report')
        ->expectsOutputToContain('- error_code: '.OperationReportRun::ERROR_CODE_UNEXPECTED)
        ->assertSuccessful();
});
