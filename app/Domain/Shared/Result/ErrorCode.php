<?php

declare(strict_types=1);

namespace App\Domain\Shared\Result;

enum ErrorCode: string
{
    case ValidationError = 'VALIDATION_ERROR';

    case ClientNameRequired = 'CLIENT_NAME_REQUIRED';

    case ClientBirthDateInvalid = 'CLIENT_BIRTH_DATE_INVALID';

    case ClientBirthDateInFuture = 'CLIENT_BIRTH_DATE_IN_FUTURE';

    case ClientCpfInvalid = 'CLIENT_CPF_INVALID';

    case ClientCpfAlreadyExists = 'CLIENT_CPF_ALREADY_EXISTS';

    case ClientEmailInvalid = 'CLIENT_EMAIL_INVALID';

    case ClientEmailAlreadyExists = 'CLIENT_EMAIL_ALREADY_EXISTS';

    case UserNameRequired = 'USER_NAME_REQUIRED';

    case UserEmailInvalid = 'USER_EMAIL_INVALID';

    case UserEmailAlreadyExists = 'USER_EMAIL_ALREADY_EXISTS';

    case UserUsernameInvalid = 'USER_USERNAME_INVALID';

    case UserUsernameAlreadyExists = 'USER_USERNAME_ALREADY_EXISTS';

    case UserPasswordTooShort = 'USER_PASSWORD_TOO_SHORT';

    case OperationNotFound = 'OPERATION_NOT_FOUND';

    case OperationFirstDueDateInvalid = 'OPERATION_FIRST_DUE_DATE_INVALID';

    case OperationProposalDateInvalid = 'OPERATION_PROPOSAL_DATE_INVALID';

    case OperationPaymentDateInvalid = 'OPERATION_PAYMENT_DATE_INVALID';

    case OperationPaymentDateRequired = 'OPERATION_PAYMENT_DATE_REQUIRED';

    case OperationPaymentDateForbidden = 'OPERATION_PAYMENT_DATE_FORBIDDEN';

    case OperationRequestedValueInvalid = 'OPERATION_REQUESTED_VALUE_INVALID';

    case OperationDisbursementValueInvalid = 'OPERATION_DISBURSEMENT_VALUE_INVALID';

    case OperationTotalInterestInvalid = 'OPERATION_TOTAL_INTEREST_INVALID';

    case OperationLateRatesInvalid = 'OPERATION_LATE_RATES_INVALID';

    case OperationInstallmentsCountInvalid = 'OPERATION_INSTALLMENTS_COUNT_INVALID';

    case OperationPaidInstallmentsCountInvalid = 'OPERATION_PAID_INSTALLMENTS_COUNT_INVALID';

    case OperationInstallmentValueInvalid = 'OPERATION_INSTALLMENT_VALUE_INVALID';

    case OperationStatusTransitionInvalid = 'OPERATION_STATUS_TRANSITION_INVALID';

    case OperationStatusUnchanged = 'OPERATION_STATUS_UNCHANGED';

    case InfrastructureUnavailable = 'INFRASTRUCTURE_UNAVAILABLE';
}
