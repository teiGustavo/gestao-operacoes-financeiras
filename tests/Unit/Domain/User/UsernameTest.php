<?php

declare(strict_types=1);

use App\Domain\Shared\Result\ErrorCode;
use App\Domain\User\ValueObjects\Username;

it('normalizes username to lowercase and trims spaces', function () {
    $result = Username::fromString('  ADMIN_MAIN  ');

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->value())->toBe('admin_main');
});

it('fails when username is invalid', function (string $rawUsername) {
    $result = Username::fromString($rawUsername);

    expect($result->isFailure())->toBeTrue()
        ->and($result->firstError()?->code)->toBe(ErrorCode::UserUsernameInvalid)
        ->and($result->firstError()?->context)->toBe(['username' => $rawUsername]);
})->with([
    'too short' => 'ab',
    'contains hyphen' => 'admin-main',
    'contains uppercase without normalization due to invalid char' => 'admin*main',
    'too long' => str_repeat('a', 51),
]);

