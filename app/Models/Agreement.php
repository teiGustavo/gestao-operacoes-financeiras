<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Agreement extends Model
{
    /**
     * @return HasMany<Operation, $this>
     */
    public function operations(): HasMany
    {
        return $this->hasMany(Operation::class);
    }
}
