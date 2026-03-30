<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OperationImportRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('operations:import:status-latest')]
#[Description('Exibe o status da execucao de importacao mais recente')]
class ImportLatestOperationStatusCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $operationImportRun = OperationImportRun::query()->latest('id')->first();

        if ($operationImportRun === null) {
            $this->error('nenhuma importacao encontrada');

            return self::FAILURE;
        }

        $this->info('Status da importacao:');
        $this->line('- run_id: '.$operationImportRun->id);
        $this->line('- status: '.$operationImportRun->status);
        $this->line('- total_rows: '.$operationImportRun->total_rows);
        $this->line('- imported_rows: '.$operationImportRun->imported_rows);
        $this->line('- rejected_rows: '.$operationImportRun->rejected_rows);
        $this->line('- queued_at: '.$operationImportRun->queued_at?->format('Y-m-d H:i:s'));
        $this->line('- started_at: '.$operationImportRun->started_at?->format('Y-m-d H:i:s'));
        $this->line('- finished_at: '.$operationImportRun->finished_at?->format('Y-m-d H:i:s'));

        $totalSeconds = $operationImportRun->metrics['total'] ?? null;

        if (is_numeric($totalSeconds)) {
            $this->line('- total_seconds: '.number_format((float) $totalSeconds, 4, '.', ''));
        }

        if ($operationImportRun->failure_message !== null) {
            $this->line('- failure_message: '.$operationImportRun->failure_message);
        }

        $errorCode = $operationImportRun->resolvedErrorCode();

        if ($errorCode !== null) {
            $this->line('- error_code: '.$errorCode);
        }

        return self::SUCCESS;
    }
}
