<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Mappers\Operation;

use App\Domain\Operation\Entities\Installment;
use App\Domain\Shared\Contracts\Mapper\DomainMapperInterface;
use App\Infrastructure\Data\Operation\InstallmentData;
use InvalidArgumentException;

final class InstallmentDataMapper implements DomainMapperInterface
{
    public function toDomain(mixed $payload): mixed
    {
        if (! $payload instanceof InstallmentData) {
            throw new InvalidArgumentException('Expected InstallmentData payload for domain mapping.');
        }

        return new Installment(
            id: $payload->id,
            operationId: $payload->operationId,
            installmentNumber: $payload->installmentNumber,
            dueDate: new \DateTimeImmutable($payload->dueDate),
            value: $payload->value,
            paid: $payload->paid,
            paidAt: $payload->paidAt === null ? null : new \DateTimeImmutable($payload->paidAt),
            paidByUserId: $payload->paidByUserId,
        );
    }

    public function toPersistence(mixed $payload): mixed
    {
        if (! $payload instanceof Installment) {
            throw new InvalidArgumentException('Expected Installment payload for persistence mapping.');
        }

        return new InstallmentData(
            id: $payload->id,
            operationId: $payload->operationId,
            installmentNumber: $payload->installmentNumber,
            dueDate: $payload->dueDate->format('Y-m-d'),
            value: $payload->value,
            paid: $payload->paid,
            paidAt: $payload->paidAt?->format('Y-m-d H:i:s'),
            paidByUserId: $payload->paidByUserId,
        );
    }
}

