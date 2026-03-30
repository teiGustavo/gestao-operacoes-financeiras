<?php

declare(strict_types=1);

use App\Domain\Client\ClientGender;
use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\ProductType;
use App\Infrastructure\Report\Jobs\FinalizeOperationCsvExportRunJob;
use App\Infrastructure\Report\Jobs\ProcessOperationCsvExportChunkJob;
use App\Infrastructure\Report\Jobs\ProcessOperationCsvExportJob;
use App\Infrastructure\Report\OperationCsvReportGenerator;
use App\Models\Agreement;
use App\Models\Client;
use App\Models\Installment;
use App\Models\Operation;
use App\Models\OperationReportRun;
use App\Models\OperationReportRunChunk;
use App\Models\User;
use App\Notifications\OperationReportFinishedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('processes queued report export, stores csv, and notifies requester', function () {
    config()->set('imports.parallel_workers', 2);

    Notification::fake();
    Storage::fake('local');

    $user = User::factory()->create();
    $agreement = Agreement::query()->create(['name' => 'Convenio CSV']);

    $operation = createOperationForAsyncReport(
        clientName: 'Cliente Relatorio',
        clientCpf: '12345678931',
        agreementId: $agreement->id,
        status: OperationStatus::APPROVED,
        productType: ProductType::PAYROLL_LOAN,
        requestedValue: 1000,
        totalInterest: 100,
        lateFeeRate: 2,
        lateInterestRate: 1,
    );

    Installment::query()->create([
        'operation_id' => $operation->id,
        'installment_number' => 1,
        'due_date' => '2026-06-20',
        'value' => 100,
        'paid' => false,
        'paid_at' => null,
        'paid_by_user_id' => null,
    ]);

    Installment::query()->create([
        'operation_id' => $operation->id,
        'installment_number' => 2,
        'due_date' => '2026-07-10',
        'value' => 100,
        'paid' => false,
        'paid_at' => null,
        'paid_by_user_id' => null,
    ]);

    $reportRun = OperationReportRun::query()->create([
        'status' => OperationReportRun::STATUS_PENDING,
        'requested_by_user_id' => $user->id,
        'filters' => [
            'status' => OperationStatus::APPROVED->value,
            'agreement' => $agreement->id,
            'product' => ProductType::PAYROLL_LOAN->value,
        ],
        'reference_date' => '2026-06-30',
        'queued_at' => now(),
    ]);

    $job = new ProcessOperationCsvExportJob($reportRun->id);
    $job->handle(app(OperationCsvReportGenerator::class));

    $chunkIds = OperationReportRunChunk::query()
        ->where('operation_report_run_id', $reportRun->id)
        ->pluck('id');

    foreach ($chunkIds as $chunkId) {
        $chunkJob = new ProcessOperationCsvExportChunkJob((int) $chunkId);
        $chunkJob->handle(app(OperationCsvReportGenerator::class));
    }

    $finalizeJob = new FinalizeOperationCsvExportRunJob($reportRun->id);
    $finalizeJob->handle(app(OperationCsvReportGenerator::class));

    $reportRun->refresh();

    expect($reportRun->status)->toBe(OperationReportRun::STATUS_COMPLETED)
        ->and($reportRun->output_file_path)->toBe('reports/operations-report-run-'.$reportRun->id.'.csv')
        ->and($reportRun->finished_at)->not->toBeNull()
        ->and($reportRun->total_rows)->toBe(1)
        ->and($reportRun->metrics)->toBeArray()
        ->and($reportRun->metrics['total'] ?? null)->not->toBeNull();

    Storage::disk('local')->assertExists((string) $reportRun->output_file_path);

    $csvContent = Storage::disk('local')->get((string) $reportRun->output_file_path);

    expect($csvContent)->toContain('operation_code,client_name,cpf,operation_value,status,product,agreement,present_value')
        ->and($csvContent)->toContain((string) $operation->id)
        ->and($csvContent)->toContain('Cliente Relatorio')
        ->and($csvContent)->toContain('212.10');

    Notification::assertSentTo($user, OperationReportFinishedNotification::class, function (OperationReportFinishedNotification $notification) use ($reportRun, $user): bool {
        $payload = $notification->toArray($user);

        return ($notification->toArray($user)['run_id'] ?? null) === $reportRun->id
            && ($notification->toArray($user)['status'] ?? null) === OperationReportRun::STATUS_COMPLETED
            && array_key_exists('error_code', $payload)
            && $payload['error_code'] === null;
    });

    $chunks = OperationReportRunChunk::query()
        ->where('operation_report_run_id', $reportRun->id)
        ->orderBy('chunk_index')
        ->get();

    expect($chunks)->toHaveCount(1)
        ->and($chunks->first()?->status)->toBe('completed');
});

it('splits export run ranges according to configured workers', function () {
    config()->set('imports.parallel_workers', 4);

    $agreement = Agreement::query()->create(['name' => 'Convenio Split']);

    for ($index = 1; $index <= 10; $index++) {
        $operation = createOperationForAsyncReport(
            clientName: 'Cliente '.$index,
            clientCpf: str_pad((string) (12345678900 + $index), 11, '0', STR_PAD_LEFT),
            agreementId: $agreement->id,
            status: OperationStatus::APPROVED,
            productType: ProductType::PAYROLL_LOAN,
            requestedValue: 1000,
            totalInterest: 100,
            lateFeeRate: 2,
            lateInterestRate: 1,
        );

        Installment::query()->create([
            'operation_id' => $operation->id,
            'installment_number' => 1,
            'due_date' => '2026-06-20',
            'value' => 100,
            'paid' => false,
            'paid_at' => null,
            'paid_by_user_id' => null,
        ]);
    }

    $reportRun = OperationReportRun::query()->create([
        'status' => OperationReportRun::STATUS_PENDING,
        'requested_by_user_id' => null,
        'filters' => [
            'status' => OperationStatus::APPROVED->value,
            'agreement' => $agreement->id,
            'product' => ProductType::PAYROLL_LOAN->value,
        ],
        'reference_date' => '2026-06-30',
        'queued_at' => now(),
    ]);

    $job = new ProcessOperationCsvExportJob($reportRun->id);
    $job->handle(app(OperationCsvReportGenerator::class));

    $chunks = OperationReportRunChunk::query()
        ->where('operation_report_run_id', $reportRun->id)
        ->orderBy('chunk_index')
        ->get();

    expect($chunks)->toHaveCount(4)
        ->and($chunks[0]->start_operation_id)->toBeLessThanOrEqual($chunks[0]->end_operation_id)
        ->and($chunks[1]->start_operation_id)->toBeGreaterThan($chunks[0]->end_operation_id)
        ->and($chunks[2]->start_operation_id)->toBeGreaterThan($chunks[1]->end_operation_id)
        ->and($chunks[3]->start_operation_id)->toBeGreaterThan($chunks[2]->end_operation_id);
});

function createOperationForAsyncReport(
    string $clientName,
    string $clientCpf,
    int $agreementId,
    OperationStatus $status,
    ProductType $productType,
    float $requestedValue,
    float $totalInterest,
    float $lateFeeRate,
    float $lateInterestRate,
): Operation {
    $client = Client::query()->create([
        'name' => $clientName,
        'cpf' => $clientCpf,
        'birth_date' => '1990-01-01',
        'gender' => ClientGender::OTHER->value,
        'email' => strtolower(str_replace(' ', '.', $clientName)).'@example.com',
    ]);

    return Operation::query()->create([
        'client_id' => $client->id,
        'agreement_id' => $agreementId,
        'requested_value' => $requestedValue,
        'disbursement_value' => $requestedValue - 50,
        'total_interest' => $totalInterest,
        'late_fee_rate' => $lateFeeRate,
        'late_interest_rate' => $lateInterestRate,
        'installments_count' => 12,
        'paid_installments_count' => 0,
        'installment_value' => 100,
        'status' => $status->value,
        'product_type' => $productType->value,
        'first_due_date' => '2026-06-01',
        'proposal_created_date' => '2026-05-01',
        'payment_date' => null,
    ]);
}
