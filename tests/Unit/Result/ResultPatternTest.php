<?php

declare(strict_types=1);

use App\Domain\Shared\Result\DomainError;
use App\Domain\Shared\Result\ErrorCode;
use App\Domain\Shared\Result\Result;

it('creates successful results', function () {
    $result = Result::success(['ok' => true]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->isFailure())->toBeFalse()
        ->and($result->value())->toBe(['ok' => true]);
});

it('creates failure results with structured errors', function () {
    $error = new DomainError(code: ErrorCode::ValidationError, message: 'Erro esperado.');
    $result = Result::failure($error);

    expect($result->isFailure())->toBeTrue()
        ->and($result->isSuccess())->toBeFalse()
        ->and($result->firstError()?->code)->toBe(ErrorCode::ValidationError);
});
