<?php

declare(strict_types=1);

namespace App\Domain\Client\Services;

use App\Domain\Client\ClientGender;
use App\Domain\Client\Contracts\Repositories\ClientRepositoryInterface;
use App\Domain\Client\Entities\Client;
use App\Domain\Client\ValueObjects\ClientCpf;
use App\Domain\Client\ValueObjects\ClientEmail;
use App\Domain\Shared\Result\DomainError;
use App\Domain\Shared\Result\ErrorCode;
use App\Domain\Shared\Result\Result;
use DateTimeImmutable;

final readonly class ClientRulesService
{
    /**
     * @return Result<Client>
     */
    public function buildClientForCreation(
        string $name,
        string $cpf,
        string $birthDate,
        ClientGender $gender,
        string $email,
        ClientRepositoryInterface $clientRepository,
    ): Result {
        $normalizedName = trim($name);

        if ($normalizedName === '') {
            return Result::failure(new DomainError(
                code: ErrorCode::ClientNameRequired,
                message: 'Nome do cliente e obrigatorio.',
            ));
        }

        $birthDateResult = $this->buildBirthDate($birthDate);

        if ($birthDateResult->isFailure()) {
            return Result::failure(...$birthDateResult->errors());
        }

        $cpfResult = ClientCpf::fromString($cpf);

        if ($cpfResult->isFailure()) {
            return Result::failure(...$cpfResult->errors());
        }

        $emailResult = ClientEmail::fromString($email);

        if ($emailResult->isFailure()) {
            return Result::failure(...$emailResult->errors());
        }

        $clientCpf = $cpfResult->value();
        $clientEmail = $emailResult->value();

        if ($clientRepository->existsByCpf($clientCpf->value())) {
            return Result::failure(new DomainError(
                code: ErrorCode::ClientCpfAlreadyExists,
                message: 'Ja existe cliente com este CPF.',
                context: ['cpf' => $clientCpf->value()],
            ));
        }

        if ($clientRepository->existsByEmail($clientEmail->value())) {
            return Result::failure(new DomainError(
                code: ErrorCode::ClientEmailAlreadyExists,
                message: 'Ja existe cliente com este e-mail.',
                context: ['email' => $clientEmail->value()],
            ));
        }

        return Result::success(new Client(
            id: null,
            name: $normalizedName,
            cpf: $clientCpf,
            birthDate: $birthDateResult->value(),
            gender: $gender,
            email: $clientEmail,
        ));
    }

    /**
     * @return Result<DateTimeImmutable>
     */
    private function buildBirthDate(string $birthDate): Result
    {
        $parsedDate = DateTimeImmutable::createFromFormat('Y-m-d', $birthDate);

        if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $birthDate) {
            return Result::failure(new DomainError(
                code: ErrorCode::ClientBirthDateInvalid,
                message: 'Data de nascimento deve estar no formato YYYY-MM-DD.',
            ));
        }

        if ($parsedDate > new DateTimeImmutable('today')) {
            return Result::failure(new DomainError(
                code: ErrorCode::ClientBirthDateInFuture,
                message: 'Data de nascimento nao pode ser futura.',
            ));
        }

        return Result::success($parsedDate);
    }
}
