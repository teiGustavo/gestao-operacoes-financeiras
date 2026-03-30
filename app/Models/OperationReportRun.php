<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'status',
    'requested_by_user_id',
    'filters',
    'reference_date',
    'output_file_path',
    'queued_at',
    'started_at',
    'finished_at',
    'total_rows',
    'metrics',
    'failure_message',
    'error_code',
])]
class OperationReportRun extends Model
{
    public const string STATUS_PENDING = 'pending';

    public const string STATUS_PROCESSING = 'processing';

    public const string STATUS_COMPLETED = 'completed';

    public const string STATUS_FAILED = 'failed';

    public const string ERROR_CODE_UNEXPECTED = 'UNEXPECTED_ERROR';

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pendente',
            self::STATUS_PROCESSING => 'Processando',
            self::STATUS_COMPLETED => 'Concluido',
            self::STATUS_FAILED => 'Falhou',
            default => 'Desconhecido',
        };
    }

    public function resolvedErrorCode(): ?string
    {
        if ($this->status !== self::STATUS_FAILED) {
            return null;
        }

        return $this->error_code ?? self::ERROR_CODE_UNEXPECTED;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'reference_date' => 'date',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'metrics' => 'array',
            'error_code' => 'string',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * @return HasMany<OperationReportRunChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(OperationReportRunChunk::class);
    }
}
