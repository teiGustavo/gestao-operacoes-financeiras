<?php

declare(strict_types=1);

namespace App\Application\Client\Data;

use App\Domain\Client\ClientGender;
use App\Domain\Client\Entities\Client;

final readonly class CreateClientOutput
{
    public function __construct(
        public int $id,
        public string $name,
        public string $cpf,
        public string $birthDate,
        public ClientGender $gender,
        public string $email,
    ) {}

    public static function fromClient(Client $client): self
    {
        return new self(
            id: (int) $client->id,
            name: $client->name,
            cpf: $client->cpf->value(),
            birthDate: $client->birthDate->format('Y-m-d'),
            gender: $client->gender,
            email: $client->email->value(),
        );
    }
}
