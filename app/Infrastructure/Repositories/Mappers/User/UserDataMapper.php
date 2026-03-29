<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Mappers\User;

use App\Domain\Shared\Contracts\Mapper\DomainMapperInterface;
use App\Domain\User\Entities\User;
use App\Domain\User\ValueObjects\UserEmail;
use App\Domain\User\ValueObjects\Username;
use App\Infrastructure\Data\User\UserData;
use InvalidArgumentException;

final class UserDataMapper implements DomainMapperInterface
{
    public function toDomain(mixed $payload): mixed
    {
        if (! $payload instanceof UserData) {
            throw new InvalidArgumentException('Expected UserData payload for domain mapping.');
        }

        $emailResult = UserEmail::fromString($payload->email);
        $usernameResult = Username::fromString($payload->username);

        if ($emailResult->isFailure() || $usernameResult->isFailure()) {
            throw new InvalidArgumentException('Invalid persistence payload for user mapping.');
        }

        return new User(
            id: $payload->id,
            name: $payload->name,
            email: $emailResult->value(),
            username: $usernameResult->value(),
            password: $payload->password,
        );
    }

    public function toPersistence(mixed $payload): mixed
    {
        if (! $payload instanceof User) {
            throw new InvalidArgumentException('Expected User payload for persistence mapping.');
        }

        return new UserData(
            id: $payload->id,
            name: $payload->name,
            email: $payload->email->value(),
            username: $payload->username->value(),
            password: $payload->password,
        );
    }
}
