<?php

declare(strict_types=1);

use App\Domain\Shared\Result\ErrorCode;
use App\Domain\User\Entities\User;
use App\Domain\User\Services\UserRulesService;
use App\Domain\User\ValueObjects\UserEmail;
use App\Domain\User\ValueObjects\Username;
use Tests\Support\InMemoryUserRepository;

it('returns failure when name is blank', function () {
    $service = new UserRulesService;
    $repository = new InMemoryUserRepository;

    $result = $service->buildUserForCreation(
        name: '   ',
        email: 'ana@example.com',
        username: 'ana_admin',
        password: 'secret123',
        userRepository: $repository,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::UserNameRequired);
});

it('returns failure when password is too short', function () {
    $service = new UserRulesService;
    $repository = new InMemoryUserRepository;

    $result = $service->buildUserForCreation(
        name: 'Ana Admin',
        email: 'ana@example.com',
        username: 'ana_admin',
        password: 'secret',
        userRepository: $repository,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::UserPasswordTooShort);
});

it('returns failure when email is invalid', function () {
    $service = new UserRulesService;
    $repository = new InMemoryUserRepository;

    $result = $service->buildUserForCreation(
        name: 'Ana Admin',
        email: 'ana.example.com',
        username: 'ana_admin',
        password: 'secret123',
        userRepository: $repository,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::UserEmailInvalid);
});

it('returns failure when username is invalid', function () {
    $service = new UserRulesService;
    $repository = new InMemoryUserRepository;

    $result = $service->buildUserForCreation(
        name: 'Ana Admin',
        email: 'ana@example.com',
        username: 'a!',
        password: 'secret123',
        userRepository: $repository,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::UserUsernameInvalid);
});

it('returns failure when username already exists', function () {
    $service = new UserRulesService;
    $repository = new InMemoryUserRepository;

    $repository->save(buildUser(
        id: 1,
        email: 'admin@example.com',
        username: 'admin_main',
    ));

    $result = $service->buildUserForCreation(
        name: 'Outro Admin',
        email: 'outro-admin@example.com',
        username: 'admin_main',
        password: 'secret123',
        userRepository: $repository,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::UserUsernameAlreadyExists)
        ->and($result->firstError()?->context)->toBe(['username' => 'admin_main']);
});

it('returns failure when email already exists', function () {
    $service = new UserRulesService;
    $repository = new InMemoryUserRepository;

    $existingResult = $service->buildUserForCreation(
        name: 'Admin',
        email: 'admin@example.com',
        username: 'admin_main',
        password: 'secret123',
        userRepository: $repository,
    );

    $repository->save($existingResult->value());

    $result = $service->buildUserForCreation(
        name: 'Outro Admin',
        email: 'admin@example.com',
        username: 'admin_other',
        password: 'secret123',
        userRepository: $repository,
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::UserEmailAlreadyExists);
});

it('builds a valid user when all rules pass', function () {
    $service = new UserRulesService;
    $repository = new InMemoryUserRepository;

    $result = $service->buildUserForCreation(
        name: 'Maria Financeira',
        email: 'maria@example.com',
        username: 'maria_fin',
        password: 'secret123',
        userRepository: $repository,
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value())->toBeInstanceOf(User::class)
        ->and($result->value()->name)->toBe('Maria Financeira')
        ->and($result->value()->email->value())->toBe('maria@example.com')
        ->and($result->value()->username->value())->toBe('maria_fin')
        ->and($result->value()->password)->toBe('secret123');
});

it('normalizes email and username before creating a valid user', function () {
    $service = new UserRulesService;
    $repository = new InMemoryUserRepository;

    $result = $service->buildUserForCreation(
        name: 'Maria Financeira',
        email: '  MARIA@EXAMPLE.COM  ',
        username: '  MARIA_FIN  ',
        password: 'secret123',
        userRepository: $repository,
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->email->value())->toBe('maria@example.com')
        ->and($result->value()->username->value())->toBe('maria_fin');
});

function buildUser(int $id, string $email, string $username): User
{
    $emailResult = UserEmail::fromString($email);
    $usernameResult = Username::fromString($username);

    return new User(
        id: $id,
        name: 'Usuario Teste',
        email: $emailResult->value(),
        username: $usernameResult->value(),
        password: 'secret123',
    );
}
