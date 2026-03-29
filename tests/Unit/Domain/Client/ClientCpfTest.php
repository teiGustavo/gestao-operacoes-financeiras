<?php

declare(strict_types=1);

use App\Domain\Client\ValueObjects\ClientCpf;
use App\Domain\Shared\Result\ErrorCode;

it('accepts an anonymized cpf with 14 alphanumeric characters', function () {
    $result = ClientCpf::fromString('EXP3BTMYeodP9U');

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->value())->toBe('EXP3BTMYeodP9U');
});

it('fails when anonymized cpf contains non alphanumeric characters', function () {
    $result = ClientCpf::fromString('EXP3BTMYeodP9-');

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::ClientCpfInvalid)
        ->and($result->firstError()?->context)->toBe(['cpf' => 'EXP3BTMYeodP9-']);
});

it('fails when anonymized cpf exceeds 14 characters', function (string $rawCpf) {
    $result = ClientCpf::fromString($rawCpf);

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::ClientCpfInvalid)
        ->and($result->firstError()?->context)->toBe(['cpf' => $rawCpf]);
})->with([
    'empty' => '',
    'too long' => 'EXP3BTMYeodP9UX',
]);
