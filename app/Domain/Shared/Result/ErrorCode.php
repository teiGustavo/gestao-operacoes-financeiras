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

    case InfrastructureUnavailable = 'INFRASTRUCTURE_UNAVAILABLE';
}

