<?php

declare(strict_types=1);

namespace App\Domain\Operation;

final class OperationStatusTransitions
{
    /**
     * @return array<string, list<OperationStatus>>
     */
    private static function map(): array
    {
        return [
            OperationStatus::DRAFT->value => [OperationStatus::PRE_ANALYSIS],
            OperationStatus::PRE_ANALYSIS->value => [OperationStatus::UNDER_REVIEW, OperationStatus::CANCELED],
            OperationStatus::UNDER_REVIEW->value => [OperationStatus::AWAITING_SIGNATURE, OperationStatus::CANCELED],
            OperationStatus::AWAITING_SIGNATURE->value => [OperationStatus::SIGNATURE_COMPLETED, OperationStatus::CANCELED],
            OperationStatus::SIGNATURE_COMPLETED->value => [OperationStatus::APPROVED, OperationStatus::CANCELED],
            OperationStatus::APPROVED->value => [OperationStatus::DISBURSED, OperationStatus::CANCELED],
            OperationStatus::CANCELED->value => [],
            OperationStatus::DISBURSED->value => [],
        ];
    }

    /**
     * @return list<OperationStatus>
     */
    public static function allowedFrom(OperationStatus $currentStatus): array
    {
        return self::map()[$currentStatus->value] ?? [];
    }

    public static function canTransition(OperationStatus $currentStatus, OperationStatus $nextStatus): bool
    {
        return in_array($nextStatus, self::allowedFrom($currentStatus), true);
    }

    /**
     * @return array<string, string>
     */
    public static function blockedReasonsFrom(OperationStatus $currentStatus): array
    {
        $blockedReasons = [];

        foreach (OperationStatus::cases() as $targetStatus) {
            if (self::canTransition($currentStatus, $targetStatus)) {
                continue;
            }

            $blockedReasons[$targetStatus->value] = self::reasonForBlockedTransition($currentStatus, $targetStatus);
        }

        return $blockedReasons;
    }

    private static function reasonForBlockedTransition(OperationStatus $currentStatus, OperationStatus $targetStatus): string
    {
        if ($currentStatus === $targetStatus) {
            return 'Status atual da operacao.';
        }

        if (in_array($currentStatus, [OperationStatus::CANCELED, OperationStatus::DISBURSED], true)) {
            return 'Operacao finalizada; nao permite novas transicoes.';
        }

        return 'Transicao nao permitida a partir do status '.$currentStatus->label().'.';
    }
}
