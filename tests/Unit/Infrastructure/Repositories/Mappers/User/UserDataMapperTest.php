<?php

declare(strict_types=1);

use App\Domain\User\Entities\User;
use App\Domain\User\ValueObjects\UserEmail;
use App\Domain\User\ValueObjects\Username;
use App\Infrastructure\Data\User\UserData;
use App\Infrastructure\Repositories\Mappers\User\UserDataMapper;

it('maps domain user to persistence data and back', function () {
    $mapper = new UserDataMapper;

    $user = new User(
        id: 8,
        name: 'Usuario Mapper',
        email: UserEmail::fromString('mapper@example.com')->value(),
        username: Username::fromString('mapper_user')->value(),
        password: 'secret123',
    );

    $data = $mapper->toPersistence($user);

    expect($data)->toBeInstanceOf(UserData::class)
        ->and($data->email)->toBe('mapper@example.com')
        ->and($data->username)->toBe('mapper_user');

    $mappedUser = $mapper->toDomain($data);

    expect($mappedUser)->toBeInstanceOf(User::class)
        ->and($mappedUser->id)->toBe(8)
        ->and($mappedUser->name)->toBe('Usuario Mapper');
});
