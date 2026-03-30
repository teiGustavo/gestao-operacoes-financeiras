<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\OperationReportRun;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OperationReportFinishedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly OperationReportRun $operationReportRun) {}

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
            'run_id' => $this->operationReportRun->id,
            'status' => $this->operationReportRun->status,
            'total_rows' => $this->operationReportRun->total_rows,
            'output_file_path' => $this->operationReportRun->output_file_path,
            'failure_message' => $this->operationReportRun->failure_message,
            'finished_at' => $this->operationReportRun->finished_at?->toIso8601String(),
            'download_url' => $this->operationReportRun->output_file_path === null
                ? null
                : route('operations.report.csv.download', ['operationReportRun' => $this->operationReportRun->id]),
        ];
    }
}
