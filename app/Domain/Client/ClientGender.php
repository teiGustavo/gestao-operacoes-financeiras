<?php

declare(strict_types=1);

namespace App\Domain\Client;

enum ClientGender: string
{
    case MALE = 'male';

    case FEMALE = 'female';

    case OTHER = 'other';

    case PREFER_NOT_TO_SAY = 'prefer_not_to_say';
}
