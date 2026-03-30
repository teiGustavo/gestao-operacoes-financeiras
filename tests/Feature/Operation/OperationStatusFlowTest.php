<?php

declare(strict_types=1);

use App\Domain\Client\ClientGender;
use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\ProductType;
use App\Models\Agreement;
use App\Models\Client;
use App\Models\Installment;
use App\Models\Operation;
use App\Models\OperationStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires authentication for operation detail and status update endpoints', function () {
    $operation = createOperationWithApprovedStatus();

    $this->getJson(route('operations.show', $operation))->assertUnauthorized();

    $this->withSession(['_token' => 'test-csrf-token'])
        ->patchJson(route('operations.status.update', $operation), [
            '_token' => 'test-csrf-token',
            'status' => OperationStatus::DISBURSED->value,
            'payment_date' => '2026-05-02',
        ])->assertUnauthorized();
});

it('returns operation details with status history timeline', function () {
    $operation = createOperationWithApprovedStatus();
    $user = User::factory()->create();

    OperationStatusHistory::query()->create([
        'operation_id' => $operation->id,
        'previous_status' => OperationStatus::DRAFT->value,
        'new_status' => OperationStatus::APPROVED->value,
        'changed_by_user_id' => $user->id,
        'notes' => 'Aprovacao manual',
        'changed_at' => '2026-05-01 12:00:00',
    ]);

    $this->actingAs($user)
        ->getJson(route('operations.show', $operation))
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'id',
                'status' => ['value', 'label'],
                'history' => [[
                    'new_status',
                    'changed_by_user' => ['id', 'name'],
                ]],
            ],
        ])
        ->assertJsonPath('data.id', $operation->id)
        ->assertJsonPath('data.status.value', OperationStatus::APPROVED->value)
        ->assertJsonPath('data.history.0.new_status', OperationStatus::APPROVED->value)
        ->assertJsonPath('data.history.0.changed_by_user.id', $user->id);
});

it('returns operation details with installments in json response', function () {
    $operation = createOperationWithApprovedStatus();
    $user = User::factory()->create();

    Installment::query()->create([
        'operation_id' => $operation->id,
        'installment_number' => 1,
        'due_date' => '2026-06-10',
        'value' => 105,
        'paid' => false,
        'paid_at' => null,
        'paid_by_user_id' => null,
    ]);

    Installment::query()->create([
        'operation_id' => $operation->id,
        'installment_number' => 2,
        'due_date' => '2026-07-10',
        'value' => 105,
        'paid' => true,
        'paid_at' => '2026-07-11 09:30:00',
        'paid_by_user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->getJson(route('operations.show', $operation))
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'id',
                'installments' => [[
                    'installment_number',
                    'paid',
                    'paid_by_user',
                ]],
            ],
        ])
        ->assertJsonPath('data.installments.0.installment_number', 1)
        ->assertJsonPath('data.installments.0.paid', false)
        ->assertJsonPath('data.installments.1.installment_number', 2)
        ->assertJsonPath('data.installments.1.paid', true)
        ->assertJsonPath('data.installments.1.paid_by_user.id', $user->id);
});

it('renders operation details page for authenticated users', function () {
    $operation = createOperationWithApprovedStatus();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('operations.show', $operation))
        ->assertSuccessful()
        ->assertViewIs('operations.show')
        ->assertViewHas('operation')
        ->assertViewHas('statusSelectability')
        ->assertViewHas('statusBlockedReasons')
        ->assertSee('Esteira')
        ->assertSee('Sair')
        ->assertSee('Operação #'.$operation->id)
        ->assertSee('Alterar status')
        ->assertSee('Histórico de status')
        ->assertSee('value="draft"', false)
        ->assertSee('value="approved"', false)
        ->assertSee('disabled', false)
        ->assertSee('selected', false)
        ->assertSee('Status atual');
});

it('allows detail navigation from listing and shows back link to listing', function () {
    $operation = createOperationWithApprovedStatus();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('operations.index'))
        ->assertSuccessful()
        ->assertSee(route('operations.show', ['operation' => $operation->id]), false)
        ->assertSee('Detalhes');

    $this->actingAs($user)
        ->get(route('operations.show', $operation))
        ->assertSuccessful()
        ->assertSee('Operação #'.$operation->id)
        ->assertSee(route('operations.index'), false)
        ->assertSee('Voltar para esteira');
});

it('renders installments section on operation details page', function () {
    $operation = createOperationWithApprovedStatus();
    $user = User::factory()->create();

    Installment::query()->create([
        'operation_id' => $operation->id,
        'installment_number' => 1,
        'due_date' => '2025-06-10',
        'value' => 105,
        'paid' => false,
        'paid_at' => null,
        'paid_by_user_id' => null,
    ]);

    Installment::query()->create([
        'operation_id' => $operation->id,
        'installment_number' => 2,
        'due_date' => '2026-07-10',
        'value' => 105,
        'paid' => true,
        'paid_at' => '2026-07-11 09:30:00',
        'paid_by_user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('operations.show', $operation))
        ->assertSuccessful()
        ->assertSee('Parcelas')
        ->assertSee('Parcela')
        ->assertSee('Vencimento')
        ->assertSee('105,00')
        ->assertSee('Pago')
        ->assertSee('Vencida')
        ->assertSee($user->name);
});

it('changes status to disbursed and appends history', function () {
    $operation = createOperationWithApprovedStatus();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['_token' => 'test-csrf-token'])
        ->patchJson(route('operations.status.update', $operation), [
            '_token' => 'test-csrf-token',
            'status' => OperationStatus::DISBURSED->value,
            'notes' => 'Pagamento realizado',
            'payment_date' => '2026-05-02',
        ])
        ->assertSuccessful()
        ->assertJsonStructure([
            'message',
            'data' => ['operation_id', 'status', 'payment_date'],
        ])
        ->assertJsonPath('data.status', OperationStatus::DISBURSED->value)
        ->assertJsonPath('data.payment_date', '2026-05-02');

    expect(Operation::query()->findOrFail($operation->id)->status)
        ->toBe(OperationStatus::DISBURSED);

    $historyEntry = OperationStatusHistory::query()->where('operation_id', $operation->id)->sole();

    expect($historyEntry->previous_status)->toBe(OperationStatus::APPROVED)
        ->and($historyEntry->new_status)->toBe(OperationStatus::DISBURSED)
        ->and($historyEntry->changed_by_user_id)->toBe($user->id)
        ->and($historyEntry->notes)->toBe('Pagamento realizado');
});

it('rejects invalid status transition and does not append history', function () {
    $operation = createOperationWithApprovedStatus();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['_token' => 'test-csrf-token'])
        ->patchJson(route('operations.status.update', $operation), [
            '_token' => 'test-csrf-token',
            'status' => OperationStatus::DRAFT->value,
        ])
        ->assertUnprocessable()
        ->assertJsonStructure([
            'message',
            'errors' => [[
                'code',
                'message',
                'context',
            ]],
        ])
        ->assertJsonPath('errors.0.code', 'OPERATION_STATUS_TRANSITION_INVALID');

    expect(OperationStatusHistory::query()->where('operation_id', $operation->id)->count())->toBe(0);
});

it('requires payment date when status changes to disbursed', function () {
    $operation = createOperationWithApprovedStatus();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['_token' => 'test-csrf-token'])
        ->patchJson(route('operations.status.update', $operation), [
            '_token' => 'test-csrf-token',
            'status' => OperationStatus::DISBURSED->value,
        ])
        ->assertUnprocessable()
        ->assertJsonStructure([
            'message',
            'errors' => [[
                'code',
                'message',
                'context',
            ]],
        ])
        ->assertJsonPath('errors.0.code', 'OPERATION_PAYMENT_DATE_REQUIRED');
});

it('changes status through visual form flow and redirects to details page', function () {
    $operation = createOperationWithApprovedStatus();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['_token' => 'test-csrf-token'])
        ->patch(route('operations.status.update', $operation), [
            '_token' => 'test-csrf-token',
            'status' => OperationStatus::DISBURSED->value,
            'payment_date' => '2026-05-02',
            'notes' => 'Pagamento via tela',
        ])
        ->assertRedirect(route('operations.show', $operation));

    $this->actingAs($user)
        ->get(route('operations.show', $operation))
        ->assertSuccessful()
        ->assertSee('Status da operacao atualizado com sucesso.');

    expect(Operation::query()->findOrFail($operation->id)->status)
        ->toBe(OperationStatus::DISBURSED);

    expect(OperationStatusHistory::query()->where('operation_id', $operation->id)->count())
        ->toBe(1);
});

function createOperationWithApprovedStatus(): Operation
{
    $client = Client::query()->create([
        'name' => 'Cliente Status',
        'cpf' => '00011122233',
        'birth_date' => '1990-01-01',
        'gender' => ClientGender::OTHER->value,
        'email' => 'cliente-status@example.com',
    ]);

    $agreement = Agreement::query()->create([
        'name' => 'Convenio Status',
    ]);

    return Operation::query()->create([
        'client_id' => $client->id,
        'agreement_id' => $agreement->id,
        'requested_value' => 1000,
        'disbursement_value' => 950,
        'total_interest' => 50,
        'late_fee_rate' => 2,
        'late_interest_rate' => 1,
        'installments_count' => 10,
        'paid_installments_count' => 0,
        'installment_value' => 105,
        'status' => OperationStatus::APPROVED->value,
        'product_type' => ProductType::PAYROLL_LOAN->value,
        'first_due_date' => '2026-06-01',
        'proposal_created_date' => '2026-05-01',
        'payment_date' => null,
    ]);
}
