<?php

declare(strict_types=1);

namespace App\Domain\Client\ValueObjects;

use App\Domain\Shared\Result\DomainError;
use App\Domain\Shared\Result\Result;

final readonly class ClientEmail
{
    private function __construct(private string $value)
    {
    }

    /**
     * @return Result<self>
     */
    public static function fromString(string $rawEmail): Result
    {
        $normalizedEmail = mb_strtolower(trim($rawEmail));

        if (! filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            return Result::failure(new DomainError(
                code: 'CLIENT_EMAIL_INVALID',
                message: 'E-mail informado e invalido.',
                context: ['email' => $rawEmail],
            ));
        }

        return Result::success(new self($normalizedEmail));
    }

    public function value(): string
    {
        return $this->value;
    }
}

