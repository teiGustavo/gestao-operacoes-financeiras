<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Mappers\Operation;

use App\Domain\Operation\Entities\Operation;
use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\ProductType;
use App\Domain\Shared\Contracts\Mapper\DomainMapperInterface;
use App\Infrastructure\Data\Operation\OperationData;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * @implements DomainMapperInterface<Operation, OperationData>
 */
final class OperationDataMapper implements DomainMapperInterface
{
    /**
     * @param  OperationData  $payload
     *
     * @throws InvalidArgumentException
     */
    public function toDomain($payload): Operation
    {
        if (! $payload instanceof OperationData) {
            throw new InvalidArgumentException('Expected OperationData payload for domain mapping.');
        }

        return new Operation(
            id: $payload->id,
            clientId: $payload->clientId,
            agreementId: $payload->agreementId,
            requestedValue: $payload->requestedValue,
            disbursementValue: $payload->disbursementValue,
            totalInterest: $payload->totalInterest,
            lateFeeRate: $payload->lateFeeRate,
            lateInterestRate: $payload->lateInterestRate,
            installmentsCount: $payload->installmentsCount,
            paidInstallmentsCount: $payload->paidInstallmentsCount,
            installmentValue: $payload->installmentValue,
            status: OperationStatus::from($payload->status),
            productType: ProductType::from($payload->productType),
            firstDueDate: new DateTimeImmutable($payload->firstDueDate),
            proposalCreatedDate: new DateTimeImmutable($payload->proposalCreatedDate),
            paymentDate: $payload->paymentDate === null ? null : new DateTimeImmutable($payload->paymentDate),
        );
    }

    /**
     * @param  Operation  $payload
     *
     * @throws InvalidArgumentException
     */
    public function toPersistence($payload): OperationData
    {
        if (! $payload instanceof Operation) {
            throw new InvalidArgumentException('Expected Operation payload for persistence mapping.');
        }

        return new OperationData(
            id: $payload->id,
            clientId: $payload->clientId,
            agreementId: $payload->agreementId,
            requestedValue: $payload->requestedValue,
            disbursementValue: $payload->disbursementValue,
            totalInterest: $payload->totalInterest,
            lateFeeRate: $payload->lateFeeRate,
            lateInterestRate: $payload->lateInterestRate,
            installmentsCount: $payload->installmentsCount,
            paidInstallmentsCount: $payload->paidInstallmentsCount,
            installmentValue: $payload->installmentValue,
            status: $payload->status->value,
            productType: $payload->productType->value,
            firstDueDate: $payload->firstDueDate->format('Y-m-d'),
            proposalCreatedDate: $payload->proposalCreatedDate->format('Y-m-d'),
            paymentDate: $payload->paymentDate?->format('Y-m-d'),
        );
    }
}
