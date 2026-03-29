<?php

declare(strict_types=1);

namespace App\Domain\Operation;

enum ProductType: string
{
    /**
     * Loan where installments are deducted directly from the borrower's payroll.
     * EN: PAYROLL_LOAN | PT_BR: CONSIGNADO
     */
    case PAYROLL_LOAN = 'payroll_loan';

    /**
     * Standard personal loan without direct payroll deduction.
     * EN: PERSONAL_LOAN | PT_BR: NAO_CONSIGNADO
     */
    case PERSONAL_LOAN = 'personal_loan';

    /**
     * Returns the user-friendly name for display in the interface (Language PT-BR).
     */
    public function label(): string
    {
        return match ($this) {
            self::PAYROLL_LOAN => 'Consignado',
            self::PERSONAL_LOAN => 'Não Consignado',
        };
    }
}
