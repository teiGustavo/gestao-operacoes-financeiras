<?php

declare(strict_types=1);

use App\Domain\Client\ClientGender;
use App\Domain\Operation\Contracts\Repositories\OperationRepositoryInterface;
use App\Domain\Operation\Contracts\Repositories\OperationStatusHistoryRepositoryInterface;
use App\Domain\Operation\Entities\Operation;
use App\Domain\Operation\Entities\OperationStatusHistory;
use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\ProductType;
use App\Infrastructure\Repositories\Eloquent\OperationEloquentRepository;
use App\Infrastructure\Repositories\Eloquent\OperationStatusHistoryEloquentRepository;
use App\Models\Agreement;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves operation repositories from the container', function () {
    expect(app(OperationRepositoryInterface::class))->toBeInstanceOf(OperationEloquentRepository::class)
        ->and(app(OperationStatusHistoryRepositoryInterface::class))
        ->toBeInstanceOf(OperationStatusHistoryEloquentRepository::class);
});

it('persists and retrieves operation via operation repository', function () {
    /** @var OperationRepositoryInterface $operationRepository */
    $operationRepository = app(OperationRepositoryInterface::class);

    $client = Client::query()->create([
        'name' => 'Cliente Operacao',
        'cpf' => '123.456.789-00',
        'birth_date' => '1990-01-01',
        'gender' => ClientGender::OTHER->value,
        'email' => 'cliente-operacao@example.com',
    ]);

    $agreement = Agreement::query()->create([
        'name' => 'Convenio Teste',
    ]);

    $operation = new Operation(
        id: null,
        clientId: $client->id,
        agreementId: $agreement->id,
        requestedValue: 1000,
        disbursementValue: 950,
        totalInterest: 50,
        lateFeeRate: 2,
        lateInterestRate: 1,
        installmentsCount: 10,
        paidInstallmentsCount: 0,
        installmentValue: 105,
        status: OperationStatus::DRAFT,
        productType: ProductType::PAYROLL_LOAN,
        firstDueDate: new DateTimeImmutable('2026-06-01'),
        proposalCreatedDate: new DateTimeImmutable('2026-05-01'),
        paymentDate: null,
    );

    $savedOperation = $operationRepository->save($operation);

    expect($savedOperation->id)->not->toBeNull()
        ->and($savedOperation->status)->toBe(OperationStatus::DRAFT);

    $foundOperation = $operationRepository->findById((int) $savedOperation->id);

    expect($foundOperation)->not->toBeNull()
        ->and($foundOperation?->agreementId)->toBe($agreement->id)
        ->and($foundOperation?->productType)->toBe(ProductType::PAYROLL_LOAN);
});

it('appends and lists status history entries for an operation', function () {
    /** @var OperationRepositoryInterface $operationRepository */
    $operationRepository = app(OperationRepositoryInterface::class);

    /** @var OperationStatusHistoryRepositoryInterface $historyRepository */
    $historyRepository = app(OperationStatusHistoryRepositoryInterface::class);

    $user = User::query()->forceCreate([
        'name' => 'Usuario Historico',
        'email' => 'usuario-historico@example.com',
        'username' => 'usuario_historico',
        'password' => 'password',
    ]);

    $client = Client::query()->create([
        'name' => 'Cliente Historico',
        'cpf' => '987.654.321-00',
        'birth_date' => '1991-01-01',
        'gender' => ClientGender::FEMALE->value,
        'email' => 'cliente-historico@example.com',
    ]);

    $agreement = Agreement::query()->create([
        'name' => 'Convenio Historico',
    ]);

    $operation = $operationRepository->save(new Operation(
        id: null,
        clientId: $client->id,
        agreementId: $agreement->id,
        requestedValue: 2000,
        disbursementValue: 1900,
        totalInterest: 100,
        lateFeeRate: 2,
        lateInterestRate: 1,
        installmentsCount: 12,
        paidInstallmentsCount: 0,
        installmentValue: 175,
        status: OperationStatus::APPROVED,
        productType: ProductType::PERSONAL_LOAN,
        firstDueDate: new DateTimeImmutable('2026-07-01'),
        proposalCreatedDate: new DateTimeImmutable('2026-06-01'),
        paymentDate: null,
    ));

    $historyRepository->append(new OperationStatusHistory(
        id: null,
        operationId: (int) $operation->id,
        previousStatus: OperationStatus::APPROVED,
        newStatus: OperationStatus::DISBURSED,
        changedByUserId: $user->id,
        notes: 'Pagamento realizado',
        changedAt: new DateTimeImmutable('2026-06-02 10:00:00'),
    ));

    $entries = $historyRepository->listByOperationId((int) $operation->id);

    expect($entries)->toHaveCount(1)
        ->and($entries[0]->newStatus)->toBe(OperationStatus::DISBURSED)
        ->and($entries[0]->changedByUserId)->toBe($user->id);
});
