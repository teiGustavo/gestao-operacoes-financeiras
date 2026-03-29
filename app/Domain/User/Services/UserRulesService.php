<?php

declare(strict_types=1);

namespace App\Domain\User\Services;

use App\Domain\Shared\Result\DomainError;
use App\Domain\Shared\Result\ErrorCode;
use App\Domain\Shared\Result\Result;
use App\Domain\User\Contracts\Repositories\UserRepositoryInterface;
use App\Domain\User\Entities\User;
use App\Domain\User\ValueObjects\UserEmail;
use App\Domain\User\ValueObjects\Username;

final readonly class UserRulesService
{
    /**
     * @return Result<User>
     */
    public function buildUserForCreation(
        string $name,
        string $email,
        string $username,
        string $password,
        UserRepositoryInterface $userRepository,
    ): Result {
        $normalizedName = trim($name);

        if ($normalizedName === '') {
            return Result::failure(new DomainError(
                code: ErrorCode::UserNameRequired,
                message: 'Nome do usuario e obrigatorio.',
            ));
        }

        if (mb_strlen($password) < 8) {
            return Result::failure(new DomainError(
                code: ErrorCode::UserPasswordTooShort,
                message: 'Senha deve conter no minimo 8 caracteres.',
            ));
        }

        $emailResult = UserEmail::fromString($email);
        if ($emailResult->isFailure()) {
            return Result::failure(...$emailResult->errors());
        }

        $usernameResult = Username::fromString($username);
        if ($usernameResult->isFailure()) {
            return Result::failure(...$usernameResult->errors());
        }

        $userEmail = $emailResult->value();
        $userUsername = $usernameResult->value();

        if ($userRepository->existsByEmail($userEmail->value())) {
            return Result::failure(new DomainError(
                code: ErrorCode::UserEmailAlreadyExists,
                message: 'Ja existe usuario com este e-mail.',
                context: ['email' => $userEmail->value()],
            ));
        }

        if ($userRepository->existsByUsername($userUsername->value())) {
            return Result::failure(new DomainError(
                code: ErrorCode::UserUsernameAlreadyExists,
                message: 'Ja existe usuario com este username.',
                context: ['username' => $userUsername->value()],
            ));
        }

        return Result::success(new User(
            id: null,
            name: $normalizedName,
            email: $userEmail,
            username: $userUsername,
            password: $password,
        ));
    }
}
