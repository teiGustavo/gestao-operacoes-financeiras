<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Client\Contracts\Repositories\ClientRepositoryInterface;
use App\Domain\Client\Entities\Client;

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
        return array_find($this->clients, fn ($client) => $client->cpf->value() === $cpf);

    }

    public function findByEmail(string $email): ?Client
    {
        return array_find($this->clients, fn ($client) => $client->email->value() === $email);

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
