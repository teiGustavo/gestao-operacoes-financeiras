<?php

declare(strict_types=1);

namespace App\Domain\Shared\Result;

use LogicException;

/**
 * @template TValue
 */
final class Result
{
    /**
     * @param list<DomainError> $errors
     */
    private function __construct(
        private readonly bool $success,
        private readonly mixed $value,
        private readonly array $errors,
    ) {
    }

    /**
     * @template TSuccess
     *
     * @param TSuccess $value
     *
     * @return self<TSuccess>
     */
    public static function success(mixed $value = null): self
    {
        return new self(true, $value, []);
    }

    /**
     * @return self<null>
     */
    public static function failure(DomainError ...$errors): self
    {
        return new self(false, null, $errors);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isFailure(): bool
    {
        return ! $this->success;
    }

    /**
     * @return TValue
     */
    public function value(): mixed
    {
        if ($this->isFailure()) {
            throw new LogicException('Cannot access value from a failed result.');
        }

        return $this->value;
    }

    public function valueOrNull(): mixed
    {
        return $this->value;
    }

    /**
     * @return list<DomainError>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?DomainError
    {
        return $this->errors[0] ?? null;
    }
}

