<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts\Mapper;

use InvalidArgumentException;

/**
 * @template TDomain
 * @template TPersistence
 */
interface DomainMapperInterface
{
    /**
     * @param  TPersistence  $payload
     *
     * @phpstan-param TPersistence $payload
     *
     * @return TDomain
     *
     * @phpstan-return TDomain
     *
     * @throws InvalidArgumentException
     */
    public function toDomain($payload): mixed;

    /**
     * @param  TDomain  $payload
     *
     * @phpstan-param TDomain $payload
     *
     * @return TPersistence
     *
     * @phpstan-return TPersistence
     *
     * @throws InvalidArgumentException
     */
    public function toPersistence($payload): mixed;
}
