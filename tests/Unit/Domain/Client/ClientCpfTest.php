<?php

declare(strict_types=1);

use App\Domain\Client\ValueObjects\ClientCpf;
use App\Domain\Shared\Result\ErrorCode;

it('formats cpf when input has only digits', function () {
    $result = ClientCpf::fromString('39053344705');

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->value())->toBe('390.533.447-05');
});

it('keeps same canonical format when input is masked', function () {
    $result = ClientCpf::fromString('390.533.447-05');

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->value())->toBe('390.533.447-05');
});

it('fails when cpf does not have 11 digits', function (string $rawCpf) {
    $result = ClientCpf::fromString($rawCpf);

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::ClientCpfInvalid)
        ->and($result->firstError()?->context)->toBe(['cpf' => $rawCpf]);
})->with([
    'empty' => '',
    'too short' => '1234567890',
    'too long' => '123456789012',
    'letters only' => 'abcdefghijk',
]);
