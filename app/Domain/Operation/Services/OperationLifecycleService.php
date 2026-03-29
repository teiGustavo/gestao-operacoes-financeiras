<?php

declare(strict_types=1);

namespace App\Domain\Operation\Services;

use App\Domain\Operation\Entities\Operation;
use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\ProductType;
use App\Domain\Shared\Result\DomainError;
use App\Domain\Shared\Result\ErrorCode;
use App\Domain\Shared\Result\Result;
use DateTimeImmutable;

final class OperationLifecycleService
{
    /**
     * @return Result<Operation>
     */
    public function buildOperationForCreation(
        int $clientId,
        int $agreementId,
        float $requestedValue,
        float $disbursementValue,
        float $totalInterest,
        float $lateFeeRate,
        float $lateInterestRate,
        int $installmentsCount,
        int $paidInstallmentsCount,
        float $installmentValue,
        OperationStatus $status,
        ProductType $productType,
        string $firstDueDate,
        string $proposalCreatedDate,
        ?string $paymentDate,
    ): Result {
        $firstDueDateResult = $this->parseDate($firstDueDate, ErrorCode::OperationFirstDueDateInvalid);
        if ($firstDueDateResult->isFailure()) {
            return Result::failure(...$firstDueDateResult->errors());
        }

        $proposalDateResult = $this->parseDate($proposalCreatedDate, ErrorCode::OperationProposalDateInvalid);
        if ($proposalDateResult->isFailure()) {
            return Result::failure(...$proposalDateResult->errors());
        }

        $paymentDateResult = $this->parseOptionalDate($paymentDate, ErrorCode::OperationPaymentDateInvalid);
        if ($paymentDateResult->isFailure()) {
            return Result::failure(...$paymentDateResult->errors());
        }

        $paymentDateValue = $paymentDateResult->value();
        $paymentDateConsistencyResult = $this->validatePaymentDateConsistency($status, $paymentDateValue);
        if ($paymentDateConsistencyResult->isFailure()) {
            return $paymentDateConsistencyResult;
        }

        $constraintsResult = $this->validateNumericConstraints(
            requestedValue: $requestedValue,
            disbursementValue: $disbursementValue,
            totalInterest: $totalInterest,
            lateFeeRate: $lateFeeRate,
            lateInterestRate: $lateInterestRate,
            installmentsCount: $installmentsCount,
            paidInstallmentsCount: $paidInstallmentsCount,
            installmentValue: $installmentValue,
        );

        if ($constraintsResult->isFailure()) {
            return $constraintsResult;
        }

        return Result::success(new Operation(
            id: null,
            clientId: $clientId,
            agreementId: $agreementId,
            requestedValue: $requestedValue,
            disbursementValue: $disbursementValue,
            totalInterest: $totalInterest,
            lateFeeRate: $lateFeeRate,
            lateInterestRate: $lateInterestRate,
            installmentsCount: $installmentsCount,
            paidInstallmentsCount: $paidInstallmentsCount,
            installmentValue: $installmentValue,
            status: $status,
            productType: $productType,
            firstDueDate: $firstDueDateResult->value(),
            proposalCreatedDate: $proposalDateResult->value(),
            paymentDate: $paymentDateValue,
        ));
    }

    /**
     * @return Result<Operation>
     */
    public function changeStatus(Operation $operation, OperationStatus $newStatus, ?string $paymentDate): Result
    {
        $allowedTransitions = [
            OperationStatus::DRAFT->value => [OperationStatus::PRE_ANALYSIS],
            OperationStatus::PRE_ANALYSIS->value => [OperationStatus::UNDER_REVIEW, OperationStatus::CANCELED],
            OperationStatus::UNDER_REVIEW->value => [OperationStatus::AWAITING_SIGNATURE, OperationStatus::CANCELED],
            OperationStatus::AWAITING_SIGNATURE->value => [OperationStatus::SIGNATURE_COMPLETED, OperationStatus::CANCELED],
            OperationStatus::SIGNATURE_COMPLETED->value => [OperationStatus::APPROVED, OperationStatus::CANCELED],
            OperationStatus::APPROVED->value => [OperationStatus::DISBURSED, OperationStatus::CANCELED],
            OperationStatus::CANCELED->value => [],
            OperationStatus::DISBURSED->value => [],
        ];

        if ($operation->status === $newStatus) {
            return Result::failure(new DomainError(
                code: ErrorCode::OperationStatusUnchanged,
                message: 'A operacao ja esta no status informado.',
            ));
        }

        $availableStatuses = $allowedTransitions[$operation->status->value] ?? [];

        if (! in_array($newStatus, $availableStatuses, true)) {
            return Result::failure(new DomainError(
                code: ErrorCode::OperationStatusTransitionInvalid,
                message: 'Transicao de status invalida para a operacao.',
                context: [
                    'current_status' => $operation->status->value,
                    'new_status' => $newStatus->value,
                ],
            ));
        }

        $paymentDateResult = $this->parseOptionalDate($paymentDate, ErrorCode::OperationPaymentDateInvalid);
        if ($paymentDateResult->isFailure()) {
            return Result::failure(...$paymentDateResult->errors());
        }

        $paymentDateValue = $paymentDateResult->value();

        $paymentDateConsistencyResult = $this->validatePaymentDateConsistency($newStatus, $paymentDateValue);
        if ($paymentDateConsistencyResult->isFailure()) {
            return $paymentDateConsistencyResult;
        }

        return Result::success($operation->withStatus($newStatus, $paymentDateValue));
    }

    /**
     * @return Result<DateTimeImmutable>
     */
    private function parseDate(string $rawDate, ErrorCode $errorCode): Result
    {
        $parsedDate = DateTimeImmutable::createFromFormat('Y-m-d', $rawDate);

        if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $rawDate) {
            return Result::failure(new DomainError(
                code: $errorCode,
                message: 'Data invalida. Use o formato YYYY-MM-DD.',
            ));
        }

        return Result::success($parsedDate);
    }

    /**
     * @return Result<DateTimeImmutable|null>
     */
    private function parseOptionalDate(?string $rawDate, ErrorCode $errorCode): Result
    {
        if ($rawDate === null) {
            return Result::success(null);
        }

        return $this->parseDate($rawDate, $errorCode);
    }

    /**
     * @return Result<null>
     */
    private function validatePaymentDateConsistency(OperationStatus $status, ?DateTimeImmutable $paymentDate): Result
    {
        if ($status === OperationStatus::DISBURSED && $paymentDate === null) {
            return Result::failure(new DomainError(
                code: ErrorCode::OperationPaymentDateRequired,
                message: 'Operacao paga ao cliente deve conter data de pagamento.',
            ));
        }

        if ($status !== OperationStatus::DISBURSED && $paymentDate !== null) {
            return Result::failure(new DomainError(
                code: ErrorCode::OperationPaymentDateForbidden,
                message: 'Data de pagamento so e permitida no status pago ao cliente.',
            ));
        }

        return Result::success();
    }

    /**
     * @return Result<null>
     */
    private function validateNumericConstraints(
        float $requestedValue,
        float $disbursementValue,
        float $totalInterest,
        float $lateFeeRate,
        float $lateInterestRate,
        int $installmentsCount,
        int $paidInstallmentsCount,
        float $installmentValue,
    ): Result {
        if ($requestedValue <= 0) {
            return Result::failure(new DomainError(
                code: ErrorCode::OperationRequestedValueInvalid,
                message: 'Valor solicitado deve ser maior que zero.',
            ));
        }

        if ($disbursementValue < 0) {
            return Result::failure(new DomainError(
                code: ErrorCode::OperationDisbursementValueInvalid,
                message: 'Valor de desembolso deve ser igual ou maior que zero.',
            ));
        }

        if ($totalInterest < 0) {
            return Result::failure(new DomainError(
                code: ErrorCode::OperationTotalInterestInvalid,
                message: 'Valor total de juros deve ser igual ou maior que zero.',
            ));
        }

        if ($lateFeeRate < 0 || $lateInterestRate < 0) {
            return Result::failure(new DomainError(
                code: ErrorCode::OperationLateRatesInvalid,
                message: 'Taxas de atraso devem ser iguais ou maiores que zero.',
            ));
        }

        if ($installmentsCount <= 0) {
            return Result::failure(new DomainError(
                code: ErrorCode::OperationInstallmentsCountInvalid,
                message: 'Quantidade de parcelas deve ser maior que zero.',
            ));
        }

        if ($paidInstallmentsCount < 0 || $paidInstallmentsCount > $installmentsCount) {
            return Result::failure(new DomainError(
                code: ErrorCode::OperationPaidInstallmentsCountInvalid,
                message: 'Parcelas pagas devem ser entre zero e quantidade de parcelas.',
            ));
        }

        if ($installmentValue <= 0) {
            return Result::failure(new DomainError(
                code: ErrorCode::OperationInstallmentValueInvalid,
                message: 'Valor da parcela deve ser maior que zero.',
            ));
        }

        return Result::success();
    }
}
