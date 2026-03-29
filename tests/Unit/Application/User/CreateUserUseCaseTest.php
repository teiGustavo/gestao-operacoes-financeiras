<?php

declare(strict_types=1);

use App\Application\User\Data\CreateUserInput;
use App\Application\User\UseCases\CreateUserUseCase;
use App\Domain\Shared\Result\ErrorCode;
use App\Domain\User\Services\UserRulesService;
use Tests\Support\InMemoryUserRepository;

it('returns failure result for invalid user payload', function () {
    $useCase = new CreateUserUseCase(new InMemoryUserRepository, new UserRulesService);

    $result = $useCase->execute(new CreateUserInput(
        name: ' ',
        email: 'admin@example.com',
        username: 'admin_main',
        password: 'secret123',
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::UserNameRequired);
});

it('returns output data when user is created successfully', function () {
    $useCase = new CreateUserUseCase(new InMemoryUserRepository, new UserRulesService);

    $result = $useCase->execute(new CreateUserInput(
        name: 'Admin Plataforma',
        email: 'admin@example.com',
        username: 'admin_main',
        password: 'secret123',
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->id)->toBe(1)
        ->and($result->value()->username)->toBe('admin_main');
});
