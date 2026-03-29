<?php

declare(strict_types=1);

use App\Domain\Shared\Result\ErrorCode;
use App\Domain\User\Entities\User;
use App\Domain\User\Services\UserRulesService;
use Tests\Support\InMemoryUserRepository;

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
        ->and($result->value()->email->value())->toBe('maria@example.com');
});
