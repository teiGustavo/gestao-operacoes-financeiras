<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Mappers\Client;

use App\Domain\Client\ClientGender;
use App\Domain\Client\Entities\Client;
use App\Domain\Client\ValueObjects\ClientCpf;
use App\Domain\Client\ValueObjects\ClientEmail;
use App\Domain\Shared\Contracts\Mapper\DomainMapperInterface;
use App\Infrastructure\Data\Client\ClientData;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * @implements DomainMapperInterface<Client, ClientData>
 */
final class ClientDataMapper implements DomainMapperInterface
{
    /**
     * @param  ClientData  $payload
     *
     * @throws InvalidArgumentException
     */
    public function toDomain($payload): Client
    {
        if (! $payload instanceof ClientData) {
            throw new InvalidArgumentException('Expected ClientData payload for domain mapping.');
        }

        $cpfResult = ClientCpf::fromString($payload->cpf);
        $emailResult = ClientEmail::fromString($payload->email);

        if ($cpfResult->isFailure() || $emailResult->isFailure()) {
            throw new InvalidArgumentException('Invalid persistence payload for client mapping.');
        }

        return new Client(
            id: $payload->id,
            name: $payload->name,
            cpf: $cpfResult->value(),
            birthDate: new DateTimeImmutable($payload->birthDate),
            gender: ClientGender::from($payload->gender),
            email: $emailResult->value(),
        );
    }

    /**
     * @param  Client  $payload
     *
     * @throws InvalidArgumentException
     */
    public function toPersistence($payload): ClientData
    {
        if (! $payload instanceof Client) {
            throw new InvalidArgumentException('Expected Client payload for persistence mapping.');
        }

        return new ClientData(
            id: $payload->id,
            name: $payload->name,
            cpf: $payload->cpf->value(),
            birthDate: $payload->birthDate->format('Y-m-d'),
            gender: $payload->gender->value,
            email: $payload->email->value(),
        );
    }
}
