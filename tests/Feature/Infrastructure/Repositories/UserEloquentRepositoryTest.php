<?php

declare(strict_types=1);

use App\Domain\User\Contracts\Repositories\UserRepositoryInterface;
use App\Domain\User\Entities\User as DomainUser;
use App\Domain\User\ValueObjects\UserEmail;
use App\Domain\User\ValueObjects\Username;
use App\Infrastructure\Repositories\Eloquent\UserEloquentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves user repository interface from container', function () {
    expect(app(UserRepositoryInterface::class))->toBeInstanceOf(UserEloquentRepository::class);
});

it('persists and reads user through eloquent repository', function () {
    /** @var UserRepositoryInterface $repository */
    $repository = app(UserRepositoryInterface::class);

    $user = new DomainUser(
        id: null,
        name: 'Admin Plataforma',
        email: UserEmail::fromString('admin@example.com')->value(),
        username: Username::fromString('admin_main')->value(),
        password: 'secret123',
    );

    $savedUser = $repository->save($user);

    expect($savedUser->id)->not->toBeNull()
        ->and($repository->existsByEmail('admin@example.com'))->toBeTrue()
        ->and($repository->existsByUsername('admin_main'))->toBeTrue();

    $foundById = $repository->findById((int) $savedUser->id);
    $foundByEmail = $repository->findByEmail('ADMIN@example.com');
    $foundByUsername = $repository->findByUsername('ADMIN_MAIN');

    expect($foundById)->not->toBeNull()
        ->and($foundById?->username->value())->toBe('admin_main')
        ->and($foundByEmail?->id)->toBe($savedUser->id)
        ->and($foundByUsername?->id)->toBe($savedUser->id);
});
