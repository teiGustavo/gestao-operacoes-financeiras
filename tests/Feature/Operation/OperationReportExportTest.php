<?php

declare(strict_types=1);

use App\Domain\Client\ClientGender;
use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\ProductType;
use App\Infrastructure\Report\Jobs\ProcessOperationCsvExportJob;
use App\Models\Agreement;
use App\Models\Client;
use App\Models\Installment;
use App\Models\Operation;
use App\Models\OperationReportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('redirects guests from csv report export', function () {
    $this->get(route('operations.report.csv'))->assertRedirect('/login');
});

it('queues asynchronous csv report export honoring filters', function () {
    Queue::fake();

    $user = User::factory()->create();

    $agreementA = Agreement::query()->create(['name' => 'Convenio A']);
    $agreementB = Agreement::query()->create(['name' => 'Convenio B']);

    $matchingOperation = createOperationForReport(
        clientName: 'Cliente Alvo',
        clientCpf: '12345678901',
        agreementId: $agreementA->id,
        status: OperationStatus::APPROVED,
        productType: ProductType::PAYROLL_LOAN,
        requestedValue: 1000,
        totalInterest: 100,
        lateFeeRate: 2,
        lateInterestRate: 1,
    );

    createInstallmentForReport(
        operationId: $matchingOperation->id,
        installmentNumber: 1,
        dueDate: '2026-06-20',
        value: 100,
        paid: false,
    );

    createInstallmentForReport(
        operationId: $matchingOperation->id,
        installmentNumber: 2,
        dueDate: '2026-07-10',
        value: 100,
        paid: false,
    );

    $otherOperation = createOperationForReport(
        clientName: 'Cliente Fora',
        clientCpf: '12345678902',
        agreementId: $agreementB->id,
        status: OperationStatus::CANCELED,
        productType: ProductType::PERSONAL_LOAN,
        requestedValue: 800,
        totalInterest: 40,
        lateFeeRate: 2,
        lateInterestRate: 1,
    );

    createInstallmentForReport(
        operationId: $otherOperation->id,
        installmentNumber: 1,
        dueDate: '2026-06-20',
        value: 80,
        paid: false,
    );

    $response = $this->actingAs($user)
        ->get(route('operations.report.csv', [
            'status' => OperationStatus::APPROVED->value,
            'product' => ProductType::PAYROLL_LOAN->value,
            'agreement' => $agreementA->id,
            'reference_date' => '2026-06-30',
        ]));

    $response->assertRedirect();
    $response->assertSessionHas('status', 'Exportacao de relatorio enfileirada com sucesso. Voce sera notificado ao concluir.');

    $reportRun = OperationReportRun::query()->sole();

    expect($reportRun->status)->toBe(OperationReportRun::STATUS_PENDING)
        ->and($reportRun->requested_by_user_id)->toBe($user->id)
        ->and($reportRun->reference_date?->format('Y-m-d'))->toBe('2026-06-30')
        ->and($reportRun->filters)->toBe([
            'status' => OperationStatus::APPROVED->value,
            'product' => ProductType::PAYROLL_LOAN->value,
            'agreement' => (string) $agreementA->id,
        ])
        ->and($reportRun->queued_at)->not->toBeNull();

    Queue::assertPushed(ProcessOperationCsvExportJob::class, function (ProcessOperationCsvExportJob $job) use ($reportRun): bool {
        return $job->operationReportRunId === $reportRun->id;
    });

    $this->actingAs($user)
        ->getJson(route('operations.report.csv', [
            'status' => OperationStatus::APPROVED->value,
            'reference_date' => '2026-06-30',
        ]))
        ->assertStatus(202)
        ->assertJsonPath('message', 'Exportacao de relatorio enfileirada com sucesso.')
        ->assertJsonPath('data.status', OperationReportRun::STATUS_PENDING);
});

function createOperationForReport(
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

function createInstallmentForReport(
    int $operationId,
    int $installmentNumber,
    string $dueDate,
    float $value,
    bool $paid,
): Installment {
    return Installment::query()->create([
        'operation_id' => $operationId,
        'installment_number' => $installmentNumber,
        'due_date' => $dueDate,
        'value' => $value,
        'paid' => $paid,
        'paid_at' => $paid ? $dueDate.' 12:00:00' : null,
        'paid_by_user_id' => null,
    ]);
}
