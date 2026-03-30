<?php

declare(strict_types=1);

use App\Domain\Client\ClientGender;
use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\ProductType;
use App\Models\Agreement;
use App\Models\Client;
use App\Models\Operation;
use App\Models\OperationReportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects guests from operations index', function () {
    $this->get('/operations')->assertRedirect('/login');
});

it('lists operations for authenticated users', function () {
    $user = User::factory()->create();

    $agreement = Agreement::query()->create(['name' => 'Prefeitura de Teste']);
    createOperation(
        clientName: 'Ana Silva',
        clientCpf: '12345678901',
        agreementId: $agreement->id,
        status: OperationStatus::APPROVED,
        productType: ProductType::PAYROLL_LOAN,
        requestedValue: 1500,
    );

    $response = $this->actingAs($user)
        ->getJson(route('operations.index'));

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [[
                'operation_code',
                'client_name',
                'operation_value',
                'status' => ['value', 'label'],
                'product' => ['value', 'label'],
                'agreement' => ['id', 'name'],
            ]],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ])
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.client_name', 'Ana Silva')
        ->assertJsonPath('data.0.operation_value', 1500)
        ->assertJsonPath('data.0.status.value', OperationStatus::APPROVED->value)
        ->assertJsonPath('data.0.product.value', ProductType::PAYROLL_LOAN->value)
        ->assertJsonPath('data.0.agreement.name', 'Prefeitura de Teste');
});

it('renders operations index as a visual pipeline page', function () {
    $user = User::factory()->create();
    $agreement = Agreement::query()->create(['name' => 'Convenio Visual']);

    createOperation(
        clientName: 'Maria Pipeline',
        clientCpf: '55555555555',
        agreementId: $agreement->id,
        status: OperationStatus::PRE_ANALYSIS,
        productType: ProductType::PERSONAL_LOAN,
        requestedValue: 1800,
    );

    $operationWithDetail = createOperation(
        clientName: 'Cliente Detalhe',
        clientCpf: '55555555556',
        agreementId: $agreement->id,
        status: OperationStatus::PRE_ANALYSIS,
        productType: ProductType::PAYROLL_LOAN,
        requestedValue: 1900,
    );

    $this->actingAs($user)
        ->get(route('operations.index', ['status' => OperationStatus::PRE_ANALYSIS->value]))
        ->assertSuccessful()
        ->assertViewIs('operations.index')
        ->assertViewHas('operations')
        ->assertViewHas('filters')
        ->assertViewHas('statusOptions')
        ->assertViewHas('productOptions')
        ->assertSee('Esteira de Operacoes')
        ->assertSee('Sair')
        ->assertSee('Aplicar filtros')
        ->assertSee('Maria Pipeline')
        ->assertSee('Convenio Visual')
        ->assertSee('Detalhes')
        ->assertSee(route('operations.show', ['operation' => $operationWithDetail->id]), false)
        ->assertSee('value="pre_analysis" selected', false)
        ->assertSee('value="draft"', false)
        ->assertSee('disabled', false)
        ->assertSee('Opcoes bloqueadas mostram motivo ao passar o mouse.');
});

it('updates status from quick action form on operations index', function () {
    $user = User::factory()->create();
    $agreement = Agreement::query()->create(['name' => 'Convenio Acao Rapida']);

    $operation = createOperation(
        clientName: 'Cliente Acao',
        clientCpf: '33333333333',
        agreementId: $agreement->id,
        status: OperationStatus::APPROVED,
        productType: ProductType::PAYROLL_LOAN,
        requestedValue: 1100,
    );

    $this->actingAs($user)
        ->withSession(['_token' => 'test-csrf-token'])
        ->patch(route('operations.status.update', $operation), [
            '_token' => 'test-csrf-token',
            'status' => OperationStatus::CANCELED->value,
            'redirect_to' => route('operations.index'),
            'notes' => 'Cancelamento rapido',
        ])
        ->assertRedirect(route('operations.index'));

    $this->actingAs($user)
        ->get(route('operations.index'))
        ->assertSuccessful()
        ->assertSee('Status da operacao atualizado com sucesso.');

    expect(Operation::query()->findOrFail($operation->id)->status)
        ->toBe(OperationStatus::CANCELED);
});

it('applies combined filters on operations index', function () {
    $user = User::factory()->create();

    $agreementOne = Agreement::query()->create(['name' => 'Convenio A']);
    $agreementTwo = Agreement::query()->create(['name' => 'Convenio B']);

    $expectedOperation = createOperation(
        clientName: 'Cliente Alvo',
        clientCpf: '99999999901',
        agreementId: $agreementOne->id,
        status: OperationStatus::APPROVED,
        productType: ProductType::PAYROLL_LOAN,
        requestedValue: 2500,
    );

    createOperation(
        clientName: 'Outro Cliente',
        clientCpf: '99999999902',
        agreementId: $agreementTwo->id,
        status: OperationStatus::CANCELED,
        productType: ProductType::PERSONAL_LOAN,
        requestedValue: 800,
    );

    $response = $this->actingAs($user)
        ->getJson(route('operations.index', [
            'status' => OperationStatus::APPROVED->value,
            'product' => ProductType::PAYROLL_LOAN->value,
            'agreement' => $agreementOne->id,
            'operation' => $expectedOperation->id,
        ]));

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ])
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.operation_code', $expectedOperation->id)
        ->assertJsonPath('data.0.client_name', 'Cliente Alvo');
});

it('supports pagination with per_page filter', function () {
    $user = User::factory()->create();
    $agreement = Agreement::query()->create(['name' => 'Convenio Paginacao']);

    createOperation(
        clientName: 'Cliente 1',
        clientCpf: '11111111111',
        agreementId: $agreement->id,
        status: OperationStatus::DRAFT,
        productType: ProductType::PAYROLL_LOAN,
        requestedValue: 700,
    );

    createOperation(
        clientName: 'Cliente 2',
        clientCpf: '22222222222',
        agreementId: $agreement->id,
        status: OperationStatus::DRAFT,
        productType: ProductType::PAYROLL_LOAN,
        requestedValue: 900,
    );

    $this->actingAs($user)
        ->getJson(route('operations.index', ['per_page' => 1]))
        ->assertSuccessful()
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ])
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonCount(1, 'data');
});

it('shows recent report runs with download link when completed', function () {
    $user = User::factory()->create();
    $agreement = Agreement::query()->create(['name' => 'Convenio Relatorio']);

    createOperation(
        clientName: 'Cliente Relatorio',
        clientCpf: '12121212121',
        agreementId: $agreement->id,
        status: OperationStatus::APPROVED,
        productType: ProductType::PAYROLL_LOAN,
        requestedValue: 1000,
    );

    $completedRun = OperationReportRun::query()->create([
        'status' => OperationReportRun::STATUS_COMPLETED,
        'requested_by_user_id' => $user->id,
        'filters' => ['status' => OperationStatus::APPROVED->value],
        'reference_date' => '2026-06-30',
        'queued_at' => now()->subMinute(),
        'started_at' => now()->subSeconds(50),
        'finished_at' => now(),
        'total_rows' => 1,
        'output_file_path' => 'reports/operations-report-run-1.csv',
    ]);

    OperationReportRun::query()->create([
        'status' => OperationReportRun::STATUS_PROCESSING,
        'requested_by_user_id' => $user->id,
        'filters' => ['status' => OperationStatus::APPROVED->value],
        'reference_date' => '2026-06-30',
        'queued_at' => now()->subSeconds(40),
        'started_at' => now()->subSeconds(30),
        'finished_at' => null,
        'total_rows' => 0,
        'output_file_path' => null,
    ]);

    OperationReportRun::query()->create([
        'status' => OperationReportRun::STATUS_FAILED,
        'requested_by_user_id' => $user->id,
        'filters' => ['status' => OperationStatus::APPROVED->value],
        'reference_date' => '2026-06-30',
        'queued_at' => now()->subSeconds(20),
        'started_at' => now()->subSeconds(15),
        'finished_at' => now()->subSeconds(10),
        'total_rows' => 0,
        'output_file_path' => null,
        'failure_message' => 'arquivo temporario nao encontrado',
    ]);

    $this->actingAs($user)
        ->get(route('operations.index'))
        ->assertSuccessful()
        ->assertSee('Ultimos relatorios')
        ->assertSee('Concluido')
        ->assertSee('Processando')
        ->assertSee('Falhou')
        ->assertSee('Motivo da falha')
        ->assertSee('arquivo temporario nao encontrado')
        ->assertSee('Baixar CSV')
        ->assertSee(route('operations.report.csv.download', ['operationReportRun' => $completedRun->id]), false)
        ->assertSee('Aguardando');
});

function createOperation(
    string $clientName,
    string $clientCpf,
    int $agreementId,
    OperationStatus $status,
    ProductType $productType,
    float $requestedValue,
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
        'total_interest' => 50,
        'late_fee_rate' => 2,
        'late_interest_rate' => 1,
        'installments_count' => 12,
        'paid_installments_count' => 0,
        'installment_value' => 120,
        'status' => $status->value,
        'product_type' => $productType->value,
        'first_due_date' => '2026-06-01',
        'proposal_created_date' => '2026-05-01',
        'payment_date' => null,
    ]);
}
