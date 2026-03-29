<?php

declare(strict_types=1);

namespace App\Domain\Operation\Contracts\Repositories;

use App\Domain\Operation\OperationStatus;
use DateTimeInterface;

interface OperationRepositoryInterface
{
    public function findById(int $operationId): ?array;

    /**
     * @param array{
     *     id?: int,
     *     client_id: int,
     *     agreement_id: int,
     *     requested_value: float|int|string,
     *     disbursement_value: float|int|string,
     *     total_interest: float|int|string,
     *     late_fee_rate: float|int|string,
     *     late_interest_rate: float|int|string,
     *     installments_count: int,
     *     paid_installments_count: int,
     *     installment_value: float|int|string,
     *     status: OperationStatus,
     *     product_type: string,
     *     first_due_date: string,
     *     proposal_created_date: string,
     *     payment_date?: DateTimeInterface|null
     * } $operationPayload
     */
    public function save(array $operationPayload): array;

    public function updateStatus(
        int $operationId,
        OperationStatus $newStatus,
        ?DateTimeInterface $paymentDate = null,
    ): void;
}

