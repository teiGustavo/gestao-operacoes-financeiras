<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Persistence;

use DateTimeImmutable;
use InvalidArgumentException;

final class OperationImportPayloadBuilder
{
    /**
     * @param  array<string, string>  $row
     * @param  array<string, int>  $clientIdByCpf
     * @param  array<string, int>  $clientIdByEmail
     * @param  array<string, int>  $agreementIdMap
     * @return array<string, mixed>
     */
    public function buildOperationPayload(
        array $row,
        array $clientIdByCpf,
        array $clientIdByEmail,
        array $agreementIdMap,
        string $timestamp,
    ): array {
        $cpf = $row['cpf'];
        $email = $row['email'];
        $clientId = $clientIdByCpf[$cpf] ?? $clientIdByEmail[$email] ?? null;

        if ($clientId === null) {
            throw new InvalidArgumentException('cliente nao encontrado apos upsert');
        }

        $resolvedAgreementId = $agreementIdMap[$row['conveniada_id']] ?? $agreementIdMap[''] ?? null;

        if ($resolvedAgreementId === null) {
            throw new InvalidArgumentException('conveniada_id: conveniada nao encontrada apos upsert');
        }

        return [
            'client_id' => $clientId,
            'agreement_id' => $resolvedAgreementId,
            'requested_value' => (float) $row['valor_requerido'],
            'disbursement_value' => (float) $row['valor_desembolso'],
            'total_interest' => (float) $row['total_juros'],
            'late_fee_rate' => (float) $row['taxa_multa'],
            'late_interest_rate' => (float) $row['taxa_mora'],
            'installments_count' => (int) $row['quantidade_parcelas'],
            'paid_installments_count' => (int) $row['quantidade_parcelas_pagas'],
            'installment_value' => (float) $row['valor_parcela'],
            'status' => $row['status_id'],
            'product_type' => $row['produto'],
            'first_due_date' => $row['data_primeiro_vencimento'],
            'proposal_created_date' => $row['data_criacao'],
            'payment_date' => $row['data_pagamento'] === '' ? null : $row['data_pagamento'],
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @return list<array<string, mixed>>
     */
    public function buildInstallmentsPayload(
        array $row,
        int $operationId,
        string $timestamp,
        string $paidAtFallback,
    ): array {
        $firstDueDateObject = new DateTimeImmutable($row['data_primeiro_vencimento']);
        $installmentsCount = (int) $row['quantidade_parcelas'];
        $paidInstallmentsCount = (int) $row['quantidade_parcelas_pagas'];
        $installmentValue = (float) $row['valor_parcela'];
        $paymentDate = $row['data_pagamento'] === '' ? null : $row['data_pagamento'];

        $payload = [];

        for ($installmentNumber = 1; $installmentNumber <= $installmentsCount; $installmentNumber++) {
            $dueDate = $firstDueDateObject->modify('+'.($installmentNumber - 1).' month');
            $isPaid = $installmentNumber <= $paidInstallmentsCount;

            $payload[] = [
                'operation_id' => $operationId,
                'installment_number' => $installmentNumber,
                'due_date' => $dueDate->format('Y-m-d'),
                'value' => $installmentValue,
                'paid' => $isPaid,
                'paid_at' => $isPaid ? ($paymentDate ?? $paidAtFallback) : null,
                'paid_by_user_id' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        return $payload;
    }
}
