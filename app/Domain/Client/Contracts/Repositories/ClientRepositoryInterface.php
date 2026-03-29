<?php

declare(strict_types=1);

namespace App\Domain\Client\Contracts\Repositories;

interface ClientRepositoryInterface
{
    public function findById(int $clientId): ?array;

    public function findByCpf(string $cpf): ?array;

    public function findByEmail(string $email): ?array;

    /**
     * @param array{
     *     id?: int,
     *     name: string,
     *     cpf: string,
     *     birth_date: string,
     *     gender: string,
     *     email: string
     * } $clientPayload
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     cpf: string,
     *     birth_date: string,
     *     gender: string,
     *     email: string
     * }
     */
    public function save(array $clientPayload): array;
}

