<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OperationReportRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('operations:report:status-latest')]
#[Description('Exibe o status da execucao de relatorio mais recente')]
class OperationReportStatusLatestCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $operationReportRun = OperationReportRun::query()->latest('id')->first();

        if ($operationReportRun === null) {
            $this->error('nenhum relatorio encontrado');

            return self::FAILURE;
        }

        $this->info('Status do relatorio:');
        $this->line('- run_id: '.$operationReportRun->id);
        $this->line('- status: '.$operationReportRun->status);
        $this->line('- total_rows: '.$operationReportRun->total_rows);
        $this->line('- queued_at: '.$operationReportRun->queued_at?->format('Y-m-d H:i:s'));
        $this->line('- started_at: '.$operationReportRun->started_at?->format('Y-m-d H:i:s'));
        $this->line('- finished_at: '.$operationReportRun->finished_at?->format('Y-m-d H:i:s'));
        $this->line('- output_file_path: '.$operationReportRun->output_file_path);

        $totalSeconds = $operationReportRun->metrics['total'] ?? null;

        if (is_numeric($totalSeconds)) {
            $this->line('- total_seconds: '.number_format((float) $totalSeconds, 4, '.', ''));
        }

        if ($operationReportRun->failure_message !== null) {
            $this->line('- failure_message: '.$operationReportRun->failure_message);
        }

        $errorCode = $operationReportRun->resolvedErrorCode();

        if ($errorCode !== null) {
            $this->line('- error_code: '.$errorCode);
        }

        return self::SUCCESS;
    }
}
