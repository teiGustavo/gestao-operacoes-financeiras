<?php

declare(strict_types=1);

namespace App\Domain\User\Entities;

use App\Domain\User\ValueObjects\UserEmail;
use App\Domain\User\ValueObjects\Username;

final readonly class User
{
    public function __construct(
        public ?int $id,
        public string $name,
        public UserEmail $email,
        public Username $username,
        public string $password,
    ) {}

    public function withId(int $id): self
    {
        return new self(
            id: $id,
            name: $this->name,
            email: $this->email,
            username: $this->username,
            password: $this->password,
        );
    }
}
