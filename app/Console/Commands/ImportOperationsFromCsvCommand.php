<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Import\OperationCsvImporter;
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

        $startTime = microtime(true);

        $startedAt = date('Y-m-d H:i:s', (int) $startTime);
        $this->info("Comando de importacao disparado em: $startedAt");

        try {
            $this->info("Iniciando importacao do arquivo: $filePath");
            $metrics = $this->operationCsvImporter->import($filePath);

            $this->info('Tempos por etapa:');
            $this->line('- extract: '.$this->formatElapsed($metrics['extract']).'s');
            $this->line('- validate_header: '.$this->formatElapsed($metrics['validate_header']).'s');
            $this->line('- validate_rows: '.$this->formatElapsed($metrics['validate_rows']).'s');
            $this->line('- persist_rows: '.$this->formatElapsed($metrics['persist_rows']).'s');
            $this->line('  - upsert_clients: '.$this->formatElapsed($metrics['persist_breakdown']['upsert_clients']).'s');
            $this->line('  - upsert_agreements: '.$this->formatElapsed($metrics['persist_breakdown']['upsert_agreements']).'s');
            $this->line('  - load_client_ids: '.$this->formatElapsed($metrics['persist_breakdown']['load_client_ids']).'s');
            $this->line('  - insert_operations: '.$this->formatElapsed($metrics['persist_breakdown']['insert_operations']).'s');
            $this->line('  - insert_installments: '.$this->formatElapsed($metrics['persist_breakdown']['insert_installments']).'s');
            $this->line('- total(importer): '.$this->formatElapsed($metrics['total']).'s');
            $this->line('- rows: '.$metrics['rows']);

            $this->info('Importacao concluida com sucesso');

            $status = self::SUCCESS;
        } catch (InvalidArgumentException $invalidArgumentException) {
            $this->error($invalidArgumentException->getMessage());

            $status = self::FAILURE;
        } finally {
            $endTime = microtime(true);

            $finishedAt = date('Y-m-d H:i:s', (int) $endTime);
            $this->info("Comando de importacao terminado em: $finishedAt");

            $elapsedTime = $endTime - $startTime;
            $formattedElapsedTime = number_format($elapsedTime, 2, ',', '.');
            $this->info("Tempo total gasto pelo comando: $formattedElapsedTime segundos");
        }

        return $status;
    }

    private function formatElapsed(float $seconds): string
    {
        return number_format($seconds, 4, '.', '');
    }
}
