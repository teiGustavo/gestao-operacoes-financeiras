<?php

declare(strict_types=1);

use App\Application\Client\Data\CreateClientInput;
use App\Application\Client\UseCases\CreateClientUseCase;
use App\Domain\Client\ClientGender;
use App\Domain\Client\Services\ClientRulesService;
use App\Domain\Shared\Result\ErrorCode;
use Tests\Support\InMemoryClientRepository;

it('returns domain failure result for invalid payload', function () {
    $repository = new InMemoryClientRepository;
    $useCase = new CreateClientUseCase($repository, new ClientRulesService);

    $result = $useCase->execute(new CreateClientInput(
        name: ' ',
        cpf: '39053344705',
        birthDate: '1990-01-01',
        gender: ClientGender::FEMALE,
        email: 'ana@example.com',
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::ClientNameRequired);
});

it('creates and returns output data for valid payload', function () {
    $repository = new InMemoryClientRepository;
    $useCase = new CreateClientUseCase($repository, new ClientRulesService);

    $result = $useCase->execute(new CreateClientInput(
        name: 'Ana Costa',
        cpf: '39053344705',
        birthDate: '1990-01-01',
        gender: ClientGender::FEMALE,
        email: 'ana@example.com',
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->id)->toBe(1)
        ->and($result->value()->cpf)->toBe('390.533.447-05')
        ->and($result->value()->email)->toBe('ana@example.com');
});
