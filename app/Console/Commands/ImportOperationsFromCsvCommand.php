<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Import\Jobs\ProcessOperationCsvImportJob;
use App\Infrastructure\Import\OperationCsvImporter;
use App\Models\OperationImportRun;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('operations:import {file : Caminho para o arquivo CSV de operacoes} {--requested-by-user-id= : ID do usuario solicitante da importacao}')]
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
        $requestedByUserIdOption = $this->option('requested-by-user-id');

        try {
            $requestedByUserId = $this->resolveRequestedByUserId($requestedByUserIdOption);

            $this->operationCsvImporter->ensureHeaderIsValid($filePath);

            $operationImportRun = OperationImportRun::query()->create([
                'file_path' => $filePath,
                'status' => OperationImportRun::STATUS_PENDING,
                'requested_by_user_id' => $requestedByUserId,
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

    private function resolveRequestedByUserId(mixed $requestedByUserIdOption): ?int
    {
        if ($requestedByUserIdOption === null || $requestedByUserIdOption === '') {
            return null;
        }

        if (! is_numeric($requestedByUserIdOption)) {
            throw new InvalidArgumentException('requested-by-user-id invalido');
        }

        $requestedByUserId = (int) $requestedByUserIdOption;

        if (! User::query()->whereKey($requestedByUserId)->exists()) {
            throw new InvalidArgumentException("usuario solicitante {$requestedByUserId} nao encontrado");
        }

        return $requestedByUserId;
    }
}
