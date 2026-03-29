<?php

declare(strict_types=1);

use App\Domain\User\Entities\User;
use App\Domain\User\ValueObjects\UserEmail;
use App\Domain\User\ValueObjects\Username;

it('creates a new instance with id while preserving all original data', function () {
    $user = buildUserEntity(
        id: null,
        name: 'Admin Plataforma',
        email: 'admin@example.com',
        username: 'admin_main',
        password: 'secret123',
    );

    $userWithId = $user->withId(88);

    expect($userWithId)->not->toBe($user)
        ->and($userWithId->id)->toBe(88)
        ->and($userWithId->name)->toBe('Admin Plataforma')
        ->and($userWithId->email->value())->toBe('admin@example.com')
        ->and($userWithId->username->value())->toBe('admin_main')
        ->and($userWithId->password)->toBe('secret123');
});

function buildUserEntity(?int $id, string $name, string $email, string $username, string $password): User
{
    $emailResult = UserEmail::fromString($email);
    $usernameResult = Username::fromString($username);

    return new User(
        id: $id,
        name: $name,
        email: $emailResult->value(),
        username: $usernameResult->value(),
        password: $password,
    );
}

