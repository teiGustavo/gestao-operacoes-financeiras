<?php

declare(strict_types=1);

use App\Domain\Client\ClientGender;
use App\Domain\Client\Entities\Client;
use App\Domain\Client\ValueObjects\ClientCpf;
use App\Domain\Client\ValueObjects\ClientEmail;
use App\Infrastructure\Data\Client\ClientData;
use App\Infrastructure\Repositories\Mappers\Client\ClientDataMapper;

it('maps domain client to persistence data and back', function () {
    $mapper = new ClientDataMapper;

    $domainClient = new Client(
        id: 10,
        name: 'Maria Silva',
        cpf: ClientCpf::fromString('39053344705')->value(),
        birthDate: new DateTimeImmutable('1991-06-15'),
        gender: ClientGender::FEMALE,
        email: ClientEmail::fromString('maria@example.com')->value(),
    );

    $persistenceData = $mapper->toPersistence($domainClient);

    expect($persistenceData)->toBeInstanceOf(ClientData::class)
        ->and($persistenceData->cpf)->toBe('390.533.447-05')
        ->and($persistenceData->gender)->toBe(ClientGender::FEMALE->value);

    $mappedClient = $mapper->toDomain($persistenceData);

    expect($mappedClient)->toBeInstanceOf(Client::class)
        ->and($mappedClient->id)->toBe(10)
        ->and($mappedClient->email->value())->toBe('maria@example.com');
});
