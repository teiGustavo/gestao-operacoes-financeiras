<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\User\Contracts\Repositories\UserRepositoryInterface;
use App\Domain\User\Entities\User;

final class InMemoryUserRepository implements UserRepositoryInterface
{
    /**
     * @var array<int, User>
     */
    private array $users = [];

    public function findById(int $userId): ?User
    {
        return $this->users[$userId] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        return array_find($this->users, fn ($user) => $user->email->value() === mb_strtolower($email));

    }

    public function findByUsername(string $username): ?User
    {
        return array_find($this->users, fn ($user) => $user->username->value() === mb_strtolower($username));

    }

    public function existsByEmail(string $email, ?int $ignoreUserId = null): bool
    {
        foreach ($this->users as $user) {
            if ($ignoreUserId !== null && $user->id === $ignoreUserId) {
                continue;
            }

            if ($user->email->value() === mb_strtolower($email)) {
                return true;
            }
        }

        return false;
    }

    public function existsByUsername(string $username, ?int $ignoreUserId = null): bool
    {
        foreach ($this->users as $user) {
            if ($ignoreUserId !== null && $user->id === $ignoreUserId) {
                continue;
            }

            if ($user->username->value() === mb_strtolower($username)) {
                return true;
            }
        }

        return false;
    }

    public function save(User $user): User
    {
        $id = $user->id ?? (count($this->users) + 1);
        $persistedUser = $user->withId($id);
        $this->users[$id] = $persistedUser;

        return $persistedUser;
    }
}
