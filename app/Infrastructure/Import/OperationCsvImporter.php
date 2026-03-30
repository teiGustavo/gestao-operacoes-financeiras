<?php

declare(strict_types=1);

namespace App\Infrastructure\Import;

use App\Infrastructure\Exceptions\InfrastructureUnavailableException;
use App\Infrastructure\Import\Contracts\OperationImportDataExtractorInterface;
use App\Infrastructure\Import\Contracts\OperationImportRowPersisterInterface;
use App\Infrastructure\Import\Validators\OperationImportHeaderValidator;
use App\Infrastructure\Import\Validators\OperationImportRowValidator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class OperationCsvImporter
{
    private const int PERSIST_ROWS_CHUNK_SIZE = 2_000;

    /**
     * @var list<string>
     */
    private const array EXPECTED_HEADERS = [
        'nome',
        'cpf',
        'dt_nasc',
        'sexo',
        'email',
        'valor_requerido',
        'valor_desembolso',
        'total_juros',
        'status_id',
        'taxa_juros',
        'taxa_mora',
        'taxa_multa',
        'data_criacao',
        'data_pagamento',
        'produto',
        'conveniada_id',
        'quantidade_parcelas',
        'data_primeiro_vencimento',
        'valor_parcela',
        'quantidade_parcelas_pagas',
    ];

    /**
     * Create a new class instance.
     */
    public function __construct(
        private readonly OperationImportDataExtractorInterface $operationImportDataExtractor,
        private readonly OperationImportHeaderValidator $operationImportHeaderValidator,
        private readonly OperationImportRowValidator $operationImportRowValidator,
        private readonly OperationImportRowPersisterInterface $operationImportRowPersister,
    ) {}

    /**
     * @return array{extract:float,validate_header:float,validate_rows:float,persist_rows:float,total:float,rows:int,persist_breakdown:array{upsert_clients:float,upsert_agreements:float,load_client_ids:float,insert_operations:float,insert_installments:float,total:float}}
     *
     * @throws InvalidArgumentException | InfrastructureUnavailableException
     */
    public function import(string $filePath): array
    {
        $totalStart = microtime(true);

        $extractStart = microtime(true);
        $extractedData = $this->operationImportDataExtractor->extract($filePath);
        $extractElapsed = microtime(true) - $extractStart;

        $headerValidationStart = microtime(true);
        $this->operationImportHeaderValidator->validate($extractedData->headers, self::EXPECTED_HEADERS);
        $headerValidationElapsed = microtime(true) - $headerValidationStart;

        $rowValidationElapsed = 0.0;
        $persistElapsed = 0.0;
        $persistedRows = 0;
        $persistBreakdown = [
            'upsert_clients' => 0.0,
            'upsert_agreements' => 0.0,
            'load_client_ids' => 0.0,
            'insert_operations' => 0.0,
            'insert_installments' => 0.0,
            'total' => 0.0,
        ];

        try {
            DB::transaction(function () use (
                $extractedData,
                &$rowValidationElapsed,
                &$persistElapsed,
                &$persistedRows,
                &$persistBreakdown,
            ): void {
                $validatedRowsChunk = [];

                foreach ($extractedData->rows as $extractedRow) {
                    $rowValidationStart = microtime(true);
                    $validatedRowsChunk[] = $this->validateAndNormalizeRow(
                        row: $extractedRow['row'],
                        lineNumber: $extractedRow['lineNumber'],
                    );
                    $rowValidationElapsed += microtime(true) - $rowValidationStart;

                    if (count($validatedRowsChunk) >= self::PERSIST_ROWS_CHUNK_SIZE) {
                        $this->persistValidatedRowsChunk(
                            validatedRowsChunk: $validatedRowsChunk,
                            persistElapsed: $persistElapsed,
                            persistedRows: $persistedRows,
                            persistBreakdown: $persistBreakdown,
                        );
                    }
                }

                $this->persistValidatedRowsChunk(
                    validatedRowsChunk: $validatedRowsChunk,
                    persistElapsed: $persistElapsed,
                    persistedRows: $persistedRows,
                    persistBreakdown: $persistBreakdown,
                );
            });
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw $invalidArgumentException;
        } catch (Throwable $e) {
            throw new InfrastructureUnavailableException('Erro ao importar dados: '.$e->getMessage());
        }

        $totalElapsed = microtime(true) - $totalStart;

        return [
            'extract' => $extractElapsed,
            'validate_header' => $headerValidationElapsed,
            'validate_rows' => $rowValidationElapsed,
            'persist_rows' => $persistElapsed,
            'total' => $totalElapsed,
            'rows' => $persistedRows,
            'persist_breakdown' => $persistBreakdown,
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function validateAndNormalizeRow(array $row, int $lineNumber): array
    {
        return $this->operationImportRowValidator->validateAndNormalizeRow(
            row: $row,
            lineNumber: $lineNumber,
            expectedHeaders: self::EXPECTED_HEADERS,
        );
    }

    /**
     * @param  list<array<string, string>>  $validatedRowsChunk
     * @param  array{upsert_clients:float,upsert_agreements:float,load_client_ids:float,insert_operations:float,insert_installments:float,total:float}  $persistBreakdown
     */
    private function persistValidatedRowsChunk(
        array &$validatedRowsChunk,
        float &$persistElapsed,
        int &$persistedRows,
        array &$persistBreakdown,
    ): void {
        if ($validatedRowsChunk === []) {
            return;
        }

        $persistStart = microtime(true);
        $chunkBreakdown = $this->operationImportRowPersister->persistMany($validatedRowsChunk);
        $persistElapsed += microtime(true) - $persistStart;
        $persistedRows += count($validatedRowsChunk);

        foreach ($chunkBreakdown as $metric => $elapsed) {
            $persistBreakdown[$metric] += $elapsed;
        }

        $validatedRowsChunk = [];
    }
}
