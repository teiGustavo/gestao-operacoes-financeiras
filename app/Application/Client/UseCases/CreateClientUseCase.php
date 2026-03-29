<?php

declare(strict_types=1);

namespace App\Application\Client\UseCases;

use App\Application\Client\Data\CreateClientInput;
use App\Application\Client\Data\CreateClientOutput;
use App\Domain\Client\Contracts\Repositories\ClientRepositoryInterface;
use App\Domain\Client\Services\ClientRulesService;
use App\Domain\Shared\Result\Result;

final readonly class CreateClientUseCase
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private ClientRulesService $clientRulesService,
    ) {
    }

    /**
     * @return Result<CreateClientOutput>
     */
    public function execute(CreateClientInput $input): Result
    {
        $clientResult = $this->clientRulesService->buildClientForCreation(
            name: $input->name,
            cpf: $input->cpf,
            birthDate: $input->birthDate,
            gender: $input->gender,
            email: $input->email,
            clientRepository: $this->clientRepository,
        );

        if ($clientResult->isFailure()) {
            return Result::failure(...$clientResult->errors());
        }

        $savedClient = $this->clientRepository->save($clientResult->value());

        return Result::success(CreateClientOutput::fromClient($savedClient));
    }
}

