<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Operation\OperationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'operation_id',
    'previous_status',
    'new_status',
    'changed_by_user_id',
    'notes',
    'changed_at',
])]
class OperationStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'operation_status_histories';

    public $timestamps = false;

    /**
     * @return BelongsTo<Operation, $this>
     */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'previous_status' => OperationStatus::class,
            'new_status' => OperationStatus::class,
            'changed_at' => 'datetime',
        ];
    }
}
