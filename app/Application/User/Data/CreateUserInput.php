<?php

declare(strict_types=1);

namespace App\Application\User\Data;

final readonly class CreateUserInput
{
    public function __construct(
        public string $name,
        public string $email,
        public string $username,
        public string $password,
    ) {}
}
