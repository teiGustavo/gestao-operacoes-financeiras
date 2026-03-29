<?php

declare(strict_types=1);

namespace App\Domain\Client\Contracts\Repositories;

use App\Domain\Client\Entities\Client;

interface ClientRepositoryInterface
{
    public function findById(int $clientId): ?Client;

    public function findByCpf(string $cpf): ?Client;

    public function findByEmail(string $email): ?Client;

    public function existsByCpf(string $cpf, ?int $ignoreClientId = null): bool;

    public function existsByEmail(string $email, ?int $ignoreClientId = null): bool;

    public function save(Client $client): Client;
}
