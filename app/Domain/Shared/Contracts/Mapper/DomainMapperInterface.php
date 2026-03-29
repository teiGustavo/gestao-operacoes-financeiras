<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts\Mapper;

interface DomainMapperInterface
{
    public function toDomain(mixed $payload): mixed;

    public function toPersistence(mixed $payload): mixed;
}

