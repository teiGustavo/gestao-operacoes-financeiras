<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'operation_import_run_id',
    'line_number',
    'row_payload',
    'status',
    'error_message',
    'processed_at',
])]
class OperationImportStagingRow extends Model
{
    public const string STATUS_PENDING = 'pending';

    public const string STATUS_VALIDATED = 'validated';

    public const string STATUS_PERSISTED = 'persisted';

    public const string STATUS_REJECTED = 'rejected';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'row_payload' => 'array',
            'processed_at' => 'datetime',
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
