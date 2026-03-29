<?php

declare(strict_types=1);

use App\Domain\Client\ClientGender;
use App\Domain\Client\Entities\Client;
use App\Domain\Client\ValueObjects\ClientCpf;
use App\Domain\Client\ValueObjects\ClientEmail;

it('creates a new instance with id while preserving all original data', function () {
    $client = buildClientEntity(
        id: null,
        name: 'Ana Costa',
        cpf: '39053344705',
        birthDate: '1992-05-15',
        gender: ClientGender::FEMALE,
        email: 'ana@example.com',
    );

    $clientWithId = $client->withId(99);

    expect($clientWithId)->not->toBe($client)
        ->and($clientWithId->id)->toBe(99)
        ->and($clientWithId->name)->toBe('Ana Costa')
        ->and($clientWithId->cpf->value())->toBe('390.533.447-05')
        ->and($clientWithId->birthDate->format('Y-m-d'))->toBe('1992-05-15')
        ->and($clientWithId->gender)->toBe(ClientGender::FEMALE)
        ->and($clientWithId->email->value())->toBe('ana@example.com');
});

function buildClientEntity(
    ?int $id,
    string $name,
    string $cpf,
    string $birthDate,
    ClientGender $gender,
    string $email,
): Client {
    $cpfResult = ClientCpf::fromString($cpf);
    $emailResult = ClientEmail::fromString($email);

    return new Client(
        id: $id,
        name: $name,
        cpf: $cpfResult->value(),
        birthDate: new DateTimeImmutable($birthDate),
        gender: $gender,
        email: $emailResult->value(),
    );
}
