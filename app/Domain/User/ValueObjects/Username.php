<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObjects;

use App\Domain\Shared\Result\DomainError;
use App\Domain\Shared\Result\ErrorCode;
use App\Domain\Shared\Result\Result;

final readonly class Username
{
    private function __construct(private string $value) {}

    /**
     * @return Result<self>
     */
    public static function fromString(string $rawUsername): Result
    {
        $normalizedUsername = mb_strtolower(trim($rawUsername));

        if (! preg_match('/^[a-z0-9_]{3,50}$/', $normalizedUsername)) {
            return Result::failure(new DomainError(
                code: ErrorCode::UserUsernameInvalid,
                message: 'Username deve conter de 3 a 50 caracteres [a-z0-9_].',
                context: ['username' => $rawUsername],
            ));
        }

        return Result::success(new self($normalizedUsername));
    }

    public function value(): string
    {
        return $this->value;
    }
}
