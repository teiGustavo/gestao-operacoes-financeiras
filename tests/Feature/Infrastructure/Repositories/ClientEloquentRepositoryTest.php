<?php

declare(strict_types=1);

use App\Domain\Client\ClientGender;
use App\Domain\Client\Contracts\Repositories\ClientRepositoryInterface;
use App\Domain\Client\Entities\Client;
use App\Domain\Client\ValueObjects\ClientCpf;
use App\Domain\Client\ValueObjects\ClientEmail;
use App\Infrastructure\Repositories\Eloquent\ClientEloquentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves client repository interface from container', function () {
    $repository = app(ClientRepositoryInterface::class);

    expect($repository)->toBeInstanceOf(ClientEloquentRepository::class);
});

it('persists and reads client through eloquent repository', function () {
    /** @var ClientRepositoryInterface $repository */
    $repository = app(ClientRepositoryInterface::class);

    $cpfResult = ClientCpf::fromString('39053344705');
    $emailResult = ClientEmail::fromString('ana@example.com');

    $client = new Client(
        id: null,
        name: 'Ana Costa',
        cpf: $cpfResult->value(),
        birthDate: new DateTimeImmutable('1990-01-01'),
        gender: ClientGender::FEMALE,
        email: $emailResult->value(),
    );

    $savedClient = $repository->save($client);

    expect($savedClient->id)->not->toBeNull()
        ->and($repository->existsByCpf($savedClient->cpf->value()))->toBeTrue()
        ->and($repository->existsByEmail($savedClient->email->value()))->toBeTrue();

    $foundClient = $repository->findById((int) $savedClient->id);

    expect($foundClient)->not->toBeNull()
        ->and($foundClient?->name)->toBe('Ana Costa')
        ->and($foundClient?->email->value())->toBe('ana@example.com')
        ->and($foundClient?->gender)->toBe(ClientGender::FEMALE);
});

