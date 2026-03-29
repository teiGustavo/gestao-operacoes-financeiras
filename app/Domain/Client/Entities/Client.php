<?php

declare(strict_types=1);

namespace App\Domain\Client\Entities;

use App\Domain\Client\ClientGender;
use App\Domain\Client\ValueObjects\ClientCpf;
use App\Domain\Client\ValueObjects\ClientEmail;
use DateTimeImmutable;

final readonly class Client
{
    public function __construct(
        public ?int $id,
        public string $name,
        public ClientCpf $cpf,
        public DateTimeImmutable $birthDate,
        public ClientGender $gender,
        public ClientEmail $email,
    ) {
    }

    public function withId(int $id): self
    {
        return new self(
            id: $id,
            name: $this->name,
            cpf: $this->cpf,
            birthDate: $this->birthDate,
            gender: $this->gender,
            email: $this->email,
        );
    }
}

