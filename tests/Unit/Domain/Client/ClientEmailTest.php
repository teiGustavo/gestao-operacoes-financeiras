<?php

declare(strict_types=1);

use App\Domain\Client\ValueObjects\ClientEmail;
use App\Domain\Shared\Result\ErrorCode;

it('normalizes email to lowercase and trims spaces', function () {
    $result = ClientEmail::fromString('  ANA.COSTA@EXAMPLE.COM  ');

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->value())->toBe('ana.costa@example.com');
});

it('fails when email is invalid', function (string $rawEmail) {
    $result = ClientEmail::fromString($rawEmail);

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::ClientEmailInvalid)
        ->and($result->firstError()?->context)->toBe(['email' => $rawEmail]);
})->with([
    'empty' => '',
    'missing at' => 'ana.example.com',
    'missing domain' => 'ana@',
]);
