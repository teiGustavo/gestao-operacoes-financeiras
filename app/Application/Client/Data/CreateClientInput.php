<?php

declare(strict_types=1);

namespace App\Application\Client\Data;

use App\Domain\Client\ClientGender;

final readonly class CreateClientInput
{
    public function __construct(
        public string $name,
        public string $cpf,
        public string $birthDate,
        public ClientGender $gender,
        public string $email,
    ) {}
}
