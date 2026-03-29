<?php

declare(strict_types=1);

namespace App\Domain\User\Contracts\Repositories;

use App\Domain\User\Entities\User;

interface UserRepositoryInterface
{
    public function findById(int $userId): ?User;

    public function findByEmail(string $email): ?User;

    public function findByUsername(string $username): ?User;

    public function existsByEmail(string $email, ?int $ignoreUserId = null): bool;

    public function existsByUsername(string $username, ?int $ignoreUserId = null): bool;

    public function save(User $user): User;
}
