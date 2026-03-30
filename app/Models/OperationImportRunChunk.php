<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'operation_import_run_id',
    'chunk_index',
    'start_line_number',
    'end_line_number',
    'start_byte_offset',
    'status',
    'total_rows',
    'imported_rows',
    'rejected_rows',
    'error_summary',
    'metrics',
    'failure_message',
    'started_at',
    'finished_at',
])]
class OperationImportRunChunk extends Model
{
    public const string STATUS_PENDING = 'pending';

    public const string STATUS_PROCESSING = 'processing';

    public const string STATUS_COMPLETED = 'completed';

    public const string STATUS_COMPLETED_WITH_ERRORS = 'completed_with_errors';

    public const string STATUS_FAILED = 'failed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'error_summary' => 'array',
            'metrics' => 'array',
            'start_byte_offset' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
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
