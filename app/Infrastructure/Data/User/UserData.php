<?php

declare(strict_types=1);

namespace App\Infrastructure\Data\User;

use Spatie\LaravelData\Data;

final class UserData extends Data
{
    public function __construct(
        public ?int $id,
        public string $name,
        public string $email,
        public string $username,
        public string $password,
    ) {}
}
