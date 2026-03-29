<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Client\ClientGender;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'cpf', 'birth_date', 'gender', 'email'])]
class Client extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'gender' => ClientGender::class,
        ];
    }
}
