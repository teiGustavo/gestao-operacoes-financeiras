<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    'error_code',
])]
class OperationImportRun extends Model
{
    public const string STATUS_PENDING = 'pending';

    public const string STATUS_PROCESSING = 'processing';

    public const string STATUS_COMPLETED = 'completed';

    public const string STATUS_COMPLETED_WITH_ERRORS = 'completed_with_errors';

    public const string STATUS_FAILED = 'failed';

    public const string ERROR_CODE_UNEXPECTED = 'UNEXPECTED_ERROR';

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pendente',
            self::STATUS_PROCESSING => 'Processando',
            self::STATUS_COMPLETED => 'Concluida',
            self::STATUS_COMPLETED_WITH_ERRORS => 'Concluida com erros',
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
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'error_summary' => 'array',
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
     * @return HasMany<OperationImportRunError, $this>
     */
    public function errors(): HasMany
    {
        return $this->hasMany(OperationImportRunError::class);
    }

    /**
     * @return HasMany<OperationImportStagingRow, $this>
     */
    public function stagingRows(): HasMany
    {
        return $this->hasMany(OperationImportStagingRow::class);
    }

    /**
     * @return HasMany<OperationImportRunChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(OperationImportRunChunk::class);
    }
}
