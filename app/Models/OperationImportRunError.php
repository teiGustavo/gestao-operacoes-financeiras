<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'operation_import_run_id',
    'line_number',
    'message',
    'row_payload',
])]
class OperationImportRunError extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'row_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<OperationImportRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(OperationImportRun::class, 'operation_import_run_id');
    }
}
