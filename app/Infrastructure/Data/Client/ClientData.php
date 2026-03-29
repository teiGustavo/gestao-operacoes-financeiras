<?php

declare(strict_types=1);

namespace App\Infrastructure\Data\Client;

use Spatie\LaravelData\Data;

final class ClientData extends Data
{
    public function __construct(
        public ?int $id,
        public string $name,
        public string $cpf,
        public string $birthDate,
        public string $gender,
        public string $email,
    ) {
    }
}

