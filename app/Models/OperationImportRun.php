<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'file_path',
    'status',
    'requested_by_user_id',
    'queued_at',
    'started_at',
    'finished_at',
    'total_rows',
    'imported_rows',
    'rejected_rows',
    'error_summary',
    'metrics',
    'failure_message',
])]
class OperationImportRun extends Model
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
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'error_summary' => 'array',
            'metrics' => 'array',
        ];
    }
}
