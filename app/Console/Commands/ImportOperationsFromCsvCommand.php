<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Import\Jobs\ProcessOperationCsvImportJob;
use App\Infrastructure\Import\OperationCsvImporter;
use App\Models\OperationImportRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('operations:import {file : Caminho para o arquivo CSV de operacoes}')]
#[Description('Importa operacoes financeiras a partir de um arquivo CSV')]
class ImportOperationsFromCsvCommand extends Command
{
    public function __construct(private readonly OperationCsvImporter $operationCsvImporter)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filePath = (string) $this->argument('file');

        try {
            $this->operationCsvImporter->ensureHeaderIsValid($filePath);

            $operationImportRun = OperationImportRun::query()->create([
                'file_path' => $filePath,
                'status' => OperationImportRun::STATUS_PENDING,
                'queued_at' => now(),
            ]);

            dispatch(new ProcessOperationCsvImportJob($operationImportRun->id));

            $this->info('Importacao enfileirada com sucesso');
            $this->line('- run_id: '.$operationImportRun->id);

            return self::SUCCESS;
        } catch (InvalidArgumentException $invalidArgumentException) {
            $this->error($invalidArgumentException->getMessage());

            return self::FAILURE;
        }
    }
}
