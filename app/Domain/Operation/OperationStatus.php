<?php

declare(strict_types=1);

namespace App\Domain\Operation;

enum OperationStatus: string
{
    /**
     * Initial stage where the operation data is being entered into the system.
     *
     * EN: DRAFT | PT_BR: DIGITANDO
     */
    case DRAFT = 'draft';

    /**
     * Preliminary automated or manual screening before the formal credit analysis.
     *
     * EN: PRE_ANALYSIS | PT_BR: PRE-ANALISE
     */
    case PRE_ANALYSIS = 'pre_analysis';

    /**
     * The operation is currently being audited or analyzed by the credit team.
     *
     * EN: UNDER_REVIEW | PT_BR: EM_ANALISE
     */
    case UNDER_REVIEW = 'under_review';

    /**
     * Credit is approved, and the system is waiting for the customer's digital or physical signature.
     *
     * EN: AWAITING_SIGNATURE | PT_BR: PARA_ASSINATURA
     */
    case AWAITING_SIGNATURE = 'awaiting_signature';

    /**
     * The customer has signed the contract, and the signature is undergoing final validation.
     *
     * EN: SIGNATURE_COMPLETED | PT_BR: ASSINATURA_CONCLUIDA
     */
    case SIGNATURE_COMPLETED = 'signature_completed';

    /**
     * All checks are cleared, and the operation is ready for the final fund transfer.
     *
     * EN: APPROVED | PT_BR: APROVADA
     */
    case APPROVED = 'approved';

    /**
     * The operation has been aborted by the user, the client, or rejected by the system.
     *
     * EN: CANCELED | PT_BR: CANCELADA
     */
    case CANCELED = 'canceled';

    /**
     * The funds have been successfully transferred to the client's account. This is a final state.
     *
     * EN: DISBURSED | PT_BR: PAGO_AO_CLIENTE
     */
    case DISBURSED = 'disbursed';

    /**
     * Returns the user-friendly name for display in the interface (Language PT-BR).
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Digitando',
            self::PRE_ANALYSIS => 'Pré-Análise',
            self::UNDER_REVIEW => 'Em Análise',
            self::AWAITING_SIGNATURE => 'Para Assinatura',
            self::SIGNATURE_COMPLETED => 'Assinatura Concluída',
            self::APPROVED => 'Aprovada',
            self::CANCELED => 'Cancelada',
            self::DISBURSED => 'Pago ao Cliente',
        };
    }
}
