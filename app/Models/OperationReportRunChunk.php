<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'operation_report_run_id',
    'chunk_index',
    'start_operation_id',
    'end_operation_id',
    'status',
    'output_file_path',
    'total_rows',
    'metrics',
    'failure_message',
    'started_at',
    'finished_at',
])]
class OperationReportRunChunk extends Model
{
    public const string STATUS_PENDING = 'pending';

    public const string STATUS_PROCESSING = 'processing';

    public const string STATUS_COMPLETED = 'completed';

    public const string STATUS_FAILED = 'failed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metrics' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OperationReportRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(OperationReportRun::class, 'operation_report_run_id');
    }
}
