<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\OperationImportRun;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OperationImportFinishedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly OperationImportRun $operationImportRun) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'run_id' => $this->operationImportRun->id,
            'status' => $this->operationImportRun->status,
            'error_code' => $this->operationImportRun->resolvedErrorCode(),
            'total_rows' => $this->operationImportRun->total_rows,
            'imported_rows' => $this->operationImportRun->imported_rows,
            'rejected_rows' => $this->operationImportRun->rejected_rows,
            'failure_message' => $this->operationImportRun->failure_message,
            'finished_at' => $this->operationImportRun->finished_at?->toIso8601String(),
        ];
    }
}
