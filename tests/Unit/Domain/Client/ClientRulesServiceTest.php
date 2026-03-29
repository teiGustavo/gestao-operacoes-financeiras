<?php

declare(strict_types=1);

use App\Domain\Client\ClientGender;
use App\Domain\Client\Contracts\Repositories\ClientRepositoryInterface;
use App\Domain\Client\Entities\Client;
use App\Domain\Client\Services\ClientRulesService;

it('returns failure when cpf is invalid', function () {
    $service = new ClientRulesService();
    $repository = new InMemoryClientRepository();

    $result = $service->buildClientForCreation(
        name: 'Maria Silva',
        cpf: '123',
        birthDate: '1990-10-10',
        gender: ClientGender::FEMALE,
        email: 'maria@example.com',
        clientRepository: $repository,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe('CLIENT_CPF_INVALID');
});

it('returns failure when email already exists', function () {
    $service = new ClientRulesService();
    $repository = new InMemoryClientRepository();

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
        ->and($result->firstError()?->code)->toBe('CLIENT_EMAIL_ALREADY_EXISTS');
});

it('builds a valid client when all business rules pass', function () {
    $service = new ClientRulesService();
    $repository = new InMemoryClientRepository();

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
        ->and($result->value()->cpf->value())->toBe('390.533.447-05');
});

function buildClient(int $id, string $cpf, string $email): Client
{
    $cpfResult = App\Domain\Client\ValueObjects\ClientCpf::fromString($cpf);
    $emailResult = App\Domain\Client\ValueObjects\ClientEmail::fromString($email);

    return new Client(
        id: $id,
        name: 'Cliente Teste',
        cpf: $cpfResult->value(),
        birthDate: new DateTimeImmutable('1990-01-01'),
        gender: ClientGender::OTHER,
        email: $emailResult->value(),
    );
}

final class InMemoryClientRepository implements ClientRepositoryInterface
{
    /**
     * @var array<int, Client>
     */
    private array $clients = [];

    public function findById(int $clientId): ?Client
    {
        return $this->clients[$clientId] ?? null;
    }

    public function findByCpf(string $cpf): ?Client
    {
        foreach ($this->clients as $client) {
            if ($client->cpf->value() === $cpf) {
                return $client;
            }
        }

        return null;
    }

    public function findByEmail(string $email): ?Client
    {
        foreach ($this->clients as $client) {
            if ($client->email->value() === $email) {
                return $client;
            }
        }

        return null;
    }

    public function existsByCpf(string $cpf, ?int $ignoreClientId = null): bool
    {
        foreach ($this->clients as $client) {
            if ($ignoreClientId !== null && $client->id === $ignoreClientId) {
                continue;
            }

            if ($client->cpf->value() === $cpf) {
                return true;
            }
        }

        return false;
    }

    public function existsByEmail(string $email, ?int $ignoreClientId = null): bool
    {
        foreach ($this->clients as $client) {
            if ($ignoreClientId !== null && $client->id === $ignoreClientId) {
                continue;
            }

            if ($client->email->value() === $email) {
                return true;
            }
        }

        return false;
    }

    public function save(Client $client): Client
    {
        $id = $client->id ?? (count($this->clients) + 1);
        $persistedClient = $client->withId($id);
        $this->clients[$id] = $persistedClient;

        return $persistedClient;
    }
}

