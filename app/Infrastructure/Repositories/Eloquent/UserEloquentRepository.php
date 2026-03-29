<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\User\Contracts\Repositories\UserRepositoryInterface;
use App\Domain\User\Entities\User as DomainUser;
use App\Infrastructure\Data\User\UserData;
use App\Infrastructure\Exceptions\InfrastructureUnavailableException;
use App\Infrastructure\Repositories\Mappers\User\UserDataMapper;
use App\Models\User as UserModel;
use Illuminate\Database\QueryException;

final readonly class UserEloquentRepository implements UserRepositoryInterface
{
    public function __construct(
        private UserModel $userModel,
        private UserDataMapper $userDataMapper,
    ) {}

    public function findById(int $userId): ?DomainUser
    {
        try {
            $model = $this->userModel->newQuery()->find($userId);

            if ($model === null) {
                return null;
            }

            return $this->mapModelToDomain($model);
        } catch (QueryException $queryException) {
            throw new InfrastructureUnavailableException('Nao foi possivel consultar usuario por id.', 0, $queryException);
        }
    }

    public function findByEmail(string $email): ?DomainUser
    {
        try {
            $normalizedEmail = mb_strtolower($email);

            $model = $this->userModel->newQuery()
                ->where('email', $normalizedEmail)
                ->first();

            if ($model === null) {
                return null;
            }

            return $this->mapModelToDomain($model);
        } catch (QueryException $queryException) {
            throw new InfrastructureUnavailableException('Nao foi possivel consultar usuario por email.', 0, $queryException);
        }
    }

    public function findByUsername(string $username): ?DomainUser
    {
        try {
            $normalizedUsername = mb_strtolower($username);

            $model = $this->userModel->newQuery()
                ->where('username', $normalizedUsername)
                ->first();

            if ($model === null) {
                return null;
            }

            return $this->mapModelToDomain($model);
        } catch (QueryException $queryException) {
            throw new InfrastructureUnavailableException('Nao foi possivel consultar usuario por username.', 0, $queryException);
        }
    }

    public function existsByEmail(string $email, ?int $ignoreUserId = null): bool
    {
        try {
            $normalizedEmail = mb_strtolower($email);
            $query = $this->userModel->newQuery()->where('email', $normalizedEmail);

            if ($ignoreUserId !== null) {
                $query->where('id', '!=', $ignoreUserId);
            }

            return $query->exists();
        } catch (QueryException $queryException) {
            throw new InfrastructureUnavailableException('Nao foi possivel verificar email de usuario.', 0, $queryException);
        }
    }

    public function existsByUsername(string $username, ?int $ignoreUserId = null): bool
    {
        try {
            $normalizedUsername = mb_strtolower($username);
            $query = $this->userModel->newQuery()->where('username', $normalizedUsername);

            if ($ignoreUserId !== null) {
                $query->where('id', '!=', $ignoreUserId);
            }

            return $query->exists();
        } catch (QueryException $queryException) {
            throw new InfrastructureUnavailableException('Nao foi possivel verificar username de usuario.', 0, $queryException);
        }
    }

    public function save(DomainUser $user): DomainUser
    {
        try {
            $userData = $this->userDataMapper->toPersistence($user);

            if (! $userData instanceof UserData) {
                throw new InfrastructureUnavailableException('Mapper de user retornou payload invalido.');
            }

            $model = $userData->id === null
                ? $this->userModel->newInstance()
                : $this->userModel->newQuery()->findOrFail($userData->id);

            $model->name = $userData->name;
            $model->email = $userData->email;
            $model->username = $userData->username;
            $model->password = $userData->password;

            $model->save();

            return $this->mapModelToDomain($model);
        } catch (\Throwable $throwable) {
            if ($throwable instanceof InfrastructureUnavailableException) {
                throw $throwable;
            }

            throw new InfrastructureUnavailableException('Nao foi possivel persistir usuario.', 0, $throwable);
        }
    }

    private function mapModelToDomain(UserModel $userModel): DomainUser
    {
        $userData = new UserData(
            id: $userModel->id,
            name: (string) $userModel->name,
            email: (string) $userModel->email,
            username: (string) $userModel->username,
            password: (string) $userModel->password,
        );

        $mappedUser = $this->userDataMapper->toDomain($userData);

        if (! $mappedUser instanceof DomainUser) {
            throw new InfrastructureUnavailableException('Mapper de user retornou entidade invalida.');
        }

        return $mappedUser;
    }
}
