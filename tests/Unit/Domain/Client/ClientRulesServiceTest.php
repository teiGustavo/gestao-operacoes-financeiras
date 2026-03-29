<?php

declare(strict_types=1);

use App\Domain\Client\ClientGender;
use App\Domain\Client\Entities\Client;
use App\Domain\Client\Services\ClientRulesService;
use App\Domain\Client\ValueObjects\ClientCpf;
use App\Domain\Client\ValueObjects\ClientEmail;
use App\Domain\Shared\Result\ErrorCode;
use Tests\Support\InMemoryClientRepository;

it('returns failure when name is blank', function () {
    $service = new ClientRulesService;
    $repository = new InMemoryClientRepository;

    $result = $service->buildClientForCreation(
        name: '   ',
        cpf: '39053344705',
        birthDate: '1990-10-10',
        gender: ClientGender::FEMALE,
        email: 'maria@example.com',
        clientRepository: $repository,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::ClientNameRequired);
});

it('returns failure when birth date format is invalid', function () {
    $service = new ClientRulesService;
    $repository = new InMemoryClientRepository;

    $result = $service->buildClientForCreation(
        name: 'Maria Silva',
        cpf: '39053344705',
        birthDate: '10/10/1990',
        gender: ClientGender::FEMALE,
        email: 'maria@example.com',
        clientRepository: $repository,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::ClientBirthDateInvalid);
});

it('returns failure when birth date is in the future', function () {
    $service = new ClientRulesService;
    $repository = new InMemoryClientRepository;

    $result = $service->buildClientForCreation(
        name: 'Maria Silva',
        cpf: '39053344705',
        birthDate: '2999-10-10',
        gender: ClientGender::FEMALE,
        email: 'maria@example.com',
        clientRepository: $repository,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::ClientBirthDateInFuture);
});

it('returns failure when cpf is invalid', function () {
    $service = new ClientRulesService;
    $repository = new InMemoryClientRepository;

    $result = $service->buildClientForCreation(
        name: 'Maria Silva',
        cpf: '123',
        birthDate: '1990-10-10',
        gender: ClientGender::FEMALE,
        email: 'maria@example.com',
        clientRepository: $repository,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::ClientCpfInvalid);
});

it('returns failure when email is invalid', function () {
    $service = new ClientRulesService;
    $repository = new InMemoryClientRepository;

    $result = $service->buildClientForCreation(
        name: 'Maria Silva',
        cpf: '39053344705',
        birthDate: '1990-10-10',
        gender: ClientGender::FEMALE,
        email: 'maria.example.com',
        clientRepository: $repository,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::ClientEmailInvalid);
});

it('returns failure when cpf already exists', function () {
    $service = new ClientRulesService;
    $repository = new InMemoryClientRepository;

    $repository->save(buildClient(
        id: 1,
        cpf: '390.533.447-05',
        email: 'maria@example.com',
    ));

    $result = $service->buildClientForCreation(
        name: 'Joao Souza',
        cpf: '39053344705',
        birthDate: '1988-02-11',
        gender: ClientGender::MALE,
        email: 'joao@example.com',
        clientRepository: $repository,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::ClientCpfAlreadyExists)
        ->and($result->firstError()?->context)->toBe(['cpf' => '390.533.447-05']);
});

it('returns failure when email already exists', function () {
    $service = new ClientRulesService;
    $repository = new InMemoryClientRepository;

    $existingClient = buildClient(
        id: 1,
        cpf: '390.533.447-05',
        email: 'maria@example.com',
    );

    $repository->save($existingClient);

    $result = $service->buildClientForCreation(
        name: 'Joao Souza',
        cpf: '901.055.234-06',
        birthDate: '1988-02-11',
        gender: ClientGender::MALE,
        email: 'maria@example.com',
        clientRepository: $repository,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::ClientEmailAlreadyExists);
});

it('builds a valid client when all business rules pass', function () {
    $service = new ClientRulesService;
    $repository = new InMemoryClientRepository;

    $result = $service->buildClientForCreation(
        name: 'Ana Costa',
        cpf: '39053344705',
        birthDate: '1992-05-15',
        gender: ClientGender::FEMALE,
        email: 'ana@example.com',
        clientRepository: $repository,
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value())->toBeInstanceOf(Client::class)
        ->and($result->value()->name)->toBe('Ana Costa')
        ->and($result->value()->cpf->value())->toBe('390.533.447-05')
        ->and($result->value()->email->value())->toBe('ana@example.com')
        ->and($result->value()->birthDate->format('Y-m-d'))->toBe('1992-05-15');
});

it('trims name before creating a valid client', function () {
    $service = new ClientRulesService;
    $repository = new InMemoryClientRepository;

    $result = $service->buildClientForCreation(
        name: '  Ana Costa  ',
        cpf: '39053344705',
        birthDate: '1992-05-15',
        gender: ClientGender::FEMALE,
        email: 'ana@example.com',
        clientRepository: $repository,
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->name)->toBe('Ana Costa');
});

function buildClient(int $id, string $cpf, string $email): Client
{
    $cpfResult = ClientCpf::fromString($cpf);
    $emailResult = ClientEmail::fromString($email);

    return new Client(
        id: $id,
        name: 'Cliente Teste',
        cpf: $cpfResult->value(),
        birthDate: new DateTimeImmutable('1990-01-01'),
        gender: ClientGender::OTHER,
        email: $emailResult->value(),
    );
}
