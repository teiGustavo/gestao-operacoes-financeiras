<?php

declare(strict_types=1);

namespace App\Application\User\Data;

use App\Domain\User\Entities\User;

final readonly class CreateUserOutput
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $username,
    ) {}

    public static function fromUser(User $user): self
    {
        return new self(
            id: (int) $user->id,
            name: $user->name,
            email: $user->email->value(),
            username: $user->username->value(),
        );
    }
}
