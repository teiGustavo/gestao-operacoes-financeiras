<?php

declare(strict_types=1);

use App\Domain\Shared\Result\ErrorCode;
use App\Domain\User\ValueObjects\UserEmail;

it('normalizes user email to lowercase and trims spaces', function () {
    $result = UserEmail::fromString('  ADMIN@EXAMPLE.COM  ');

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->value())->toBe('admin@example.com');
});

it('fails when user email is invalid', function (string $rawEmail) {
    $result = UserEmail::fromString($rawEmail);

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::UserEmailInvalid)
        ->and($result->firstError()?->context)->toBe(['email' => $rawEmail]);
})->with([
    'empty' => '',
    'missing at' => 'admin.example.com',
    'missing domain' => 'admin@',
]);
