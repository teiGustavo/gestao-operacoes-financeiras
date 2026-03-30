<?php

declare(strict_types=1);

use App\Models\OperationReportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows latest operation report run status details', function () {
    $user = User::factory()->create();

    OperationReportRun::query()->create([
        'status' => OperationReportRun::STATUS_COMPLETED,
        'requested_by_user_id' => $user->id,
        'reference_date' => '2026-06-30',
        'queued_at' => now()->subMinutes(2),
        'started_at' => now()->subMinute(),
        'finished_at' => now()->subSeconds(20),
        'total_rows' => 1,
        'metrics' => ['total' => 0.4567],
        'output_file_path' => 'reports/older.csv',
    ]);

    $latestRun = OperationReportRun::query()->create([
        'status' => OperationReportRun::STATUS_FAILED,
        'requested_by_user_id' => $user->id,
        'reference_date' => '2026-06-30',
        'queued_at' => now()->subMinute(),
        'started_at' => now()->subSeconds(50),
        'finished_at' => now(),
        'total_rows' => 10,
        'metrics' => ['total' => 1.9876],
        'output_file_path' => null,
        'failure_message' => 'falha simulada',
    ]);

    $this->artisan('operations:report:status-latest')
        ->expectsOutputToContain('Status do relatorio:')
        ->expectsOutputToContain('- run_id: '.$latestRun->id)
        ->expectsOutputToContain('- status: '.OperationReportRun::STATUS_FAILED)
        ->expectsOutputToContain('- total_rows: 10')
        ->expectsOutputToContain('- total_seconds: 1.9876')
        ->expectsOutputToContain('- output_file_path:')
        ->expectsOutputToContain('- failure_message: falha simulada')
        ->expectsOutputToContain('- error_code: '.OperationReportRun::ERROR_CODE_UNEXPECTED)
        ->assertSuccessful();
});

it('fails when there is no operation report run to show latest status', function () {
    $this->artisan('operations:report:status-latest')
        ->expectsOutputToContain('nenhum relatorio encontrado')
        ->assertFailed();
});
