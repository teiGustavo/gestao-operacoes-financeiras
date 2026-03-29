<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use App\Application\User\Data\CreateUserInput;
use App\Application\User\Data\CreateUserOutput;
use App\Domain\Shared\Result\Result;
use App\Domain\User\Contracts\Repositories\UserRepositoryInterface;
use App\Domain\User\Services\UserRulesService;

final readonly class CreateUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserRulesService $userRulesService,
    ) {}

    /**
     * @return Result<CreateUserOutput>
     */
    public function execute(CreateUserInput $input): Result
    {
        $userResult = $this->userRulesService->buildUserForCreation(
            name: $input->name,
            email: $input->email,
            username: $input->username,
            password: $input->password,
            userRepository: $this->userRepository,
        );

        if ($userResult->isFailure()) {
            return Result::failure(...$userResult->errors());
        }

        $savedUser = $this->userRepository->save($userResult->value());

        return Result::success(CreateUserOutput::fromUser($savedUser));
    }
}
