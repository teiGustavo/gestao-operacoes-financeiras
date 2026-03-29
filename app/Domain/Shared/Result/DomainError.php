<?php

declare(strict_types=1);

namespace App\Domain\Shared\Result;

final readonly class DomainError
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public ErrorCode $code,
        public string $message,
        public array $context = [],
    ) {}
}
