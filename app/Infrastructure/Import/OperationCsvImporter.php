<?php

declare(strict_types=1);

namespace App\Infrastructure\Import;

use App\Infrastructure\Exceptions\InfrastructureUnavailableException;
use App\Infrastructure\Import\Contracts\OperationImportDataExtractorInterface;
use App\Infrastructure\Import\Contracts\OperationImportRowPersisterInterface;
use App\Infrastructure\Import\Validators\OperationImportHeaderValidator;
use App\Infrastructure\Import\Validators\OperationImportRowValidator;
use App\Models\OperationImportRunError;
use App\Models\OperationImportStagingRow;
use InvalidArgumentException;

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
     * @throws InvalidArgumentException
     */
    public function ensureHeaderIsValid(string $filePath): void
    {
        $extractedData = $this->operationImportDataExtractor->extract($filePath);

        $this->operationImportHeaderValidator->validate($extractedData->headers, self::EXPECTED_HEADERS);
    }

    /**
     * @return array{total_rows:int,imported_rows:int,rejected_rows:int,error_summary:array<string,int>,metrics:array{extract:float,validate_header:float,validate_rows:float,persist_rows:float,total:float,persist_breakdown:array{upsert_clients:float,upsert_agreements:float,load_client_ids:float,insert_operations:float,insert_installments:float,total:float}}}
     *
     * @throws InvalidArgumentException | InfrastructureUnavailableException
     */
    public function importWithSummary(
        string $filePath,
        ?int $operationImportRunId = null,
        ?int $startLineNumber = null,
        ?int $endLineNumber = null,
        bool $shouldValidateHeader = true,
    ): array {
        $totalStart = microtime(true);

        $extractStart = microtime(true);
        $extractedData = $this->operationImportDataExtractor->extract(
            filePath: $filePath,
            startLineNumber: $startLineNumber,
            endLineNumber: $endLineNumber,
        );
        $extractElapsed = microtime(true) - $extractStart;

        $headerValidationElapsed = 0.0;

        if ($shouldValidateHeader) {
            $headerValidationStart = microtime(true);
            $this->operationImportHeaderValidator->validate($extractedData->headers, self::EXPECTED_HEADERS);
            $headerValidationElapsed = microtime(true) - $headerValidationStart;
        }

        $rowValidationElapsed = 0.0;
        $persistElapsed = 0.0;
        $importedRows = 0;
        $totalRows = 0;
        $rejectedRows = 0;
        $errorSummary = [];
        $persistBreakdown = [
            'upsert_clients' => 0.0,
            'upsert_agreements' => 0.0,
            'load_client_ids' => 0.0,
            'insert_operations' => 0.0,
            'insert_installments' => 0.0,
            'total' => 0.0,
        ];

        try {
            $validatedRowsChunk = [];

            try {
                foreach ($extractedData->rows as $extractedRow) {
                    $totalRows++;
                    $lineNumber = $extractedRow['lineNumber'];

                    $this->upsertStagingRow(
                        operationImportRunId: $operationImportRunId,
                        lineNumber: $lineNumber,
                        rowPayload: $extractedRow['row'],
                        status: OperationImportStagingRow::STATUS_PENDING,
                    );

                    try {
                        $rowValidationStart = microtime(true);
                        $validatedRowsChunk[] = [
                            'line_number' => $lineNumber,
                            'row' => $this->validateAndNormalizeRow(
                                row: $extractedRow['row'],
                                lineNumber: $lineNumber,
                            ),
                        ];

                        $this->upsertStagingRow(
                            operationImportRunId: $operationImportRunId,
                            lineNumber: $lineNumber,
                            rowPayload: $extractedRow['row'],
                            status: OperationImportStagingRow::STATUS_VALIDATED,
                        );

                        $rowValidationElapsed += microtime(true) - $rowValidationStart;
                    } catch (InvalidArgumentException $invalidArgumentException) {
                        $rejectedRows++;
                        $this->appendErrorSummary($errorSummary, $invalidArgumentException->getMessage());
                        $this->recordRowError(
                            operationImportRunId: $operationImportRunId,
                            lineNumber: $lineNumber,
                            message: $invalidArgumentException->getMessage(),
                            rowPayload: $extractedRow['row'],
                        );

                        $this->upsertStagingRow(
                            operationImportRunId: $operationImportRunId,
                            lineNumber: $lineNumber,
                            rowPayload: $extractedRow['row'],
                            status: OperationImportStagingRow::STATUS_REJECTED,
                            errorMessage: $invalidArgumentException->getMessage(),
                            processedAtNow: true,
                        );

                        continue;
                    }

                    if (count($validatedRowsChunk) >= self::PERSIST_ROWS_CHUNK_SIZE) {
                        $this->persistValidatedRowsChunk(
                            validatedRowsChunk: $validatedRowsChunk,
                            persistElapsed: $persistElapsed,
                            persistedRows: $importedRows,
                            persistBreakdown: $persistBreakdown,
                            operationImportRunId: $operationImportRunId,
                        );
                    }
                }
            } catch (InvalidArgumentException $invalidArgumentException) {
                $rejectedRows++;
                $this->appendErrorSummary($errorSummary, $invalidArgumentException->getMessage());
                $this->recordRowError(
                    operationImportRunId: $operationImportRunId,
                    lineNumber: null,
                    message: $invalidArgumentException->getMessage(),
                    rowPayload: null,
                );
            }

            $this->persistValidatedRowsChunk(
                validatedRowsChunk: $validatedRowsChunk,
                persistElapsed: $persistElapsed,
                persistedRows: $importedRows,
                persistBreakdown: $persistBreakdown,
                operationImportRunId: $operationImportRunId,
            );
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw $invalidArgumentException;
        } catch (\Throwable $throwable) {
            throw new InfrastructureUnavailableException('Erro ao importar dados: '.$throwable->getMessage());
        }

        $totalElapsed = microtime(true) - $totalStart;

        if ($totalRows === 0) {
            $totalRows = $importedRows + $rejectedRows;
        }

        return [
            'total_rows' => $totalRows,
            'imported_rows' => $importedRows,
            'rejected_rows' => $rejectedRows,
            'error_summary' => $errorSummary,
            'metrics' => [
                'extract' => $extractElapsed,
                'validate_header' => $headerValidationElapsed,
                'validate_rows' => $rowValidationElapsed,
                'persist_rows' => $persistElapsed,
                'total' => $totalElapsed,
                'persist_breakdown' => $persistBreakdown,
            ],
        ];
    }

    public function countDataRows(string $filePath): int
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new InvalidArgumentException('arquivo csv nao encontrado');
        }

        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            throw new InvalidArgumentException('arquivo csv nao pode ser lido');
        }

        $lineCount = 0;

        while (fgets($handle) !== false) {
            $lineCount++;
        }

        fclose($handle);

        return max(0, $lineCount - 1);
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
     * @param  list<array{line_number:int,row:array<string,string>}>  $validatedRowsChunk
     * @param  array{upsert_clients:float,upsert_agreements:float,load_client_ids:float,insert_operations:float,insert_installments:float,total:float}  $persistBreakdown
     */
    private function persistValidatedRowsChunk(
        array &$validatedRowsChunk,
        float &$persistElapsed,
        int &$persistedRows,
        array &$persistBreakdown,
        ?int $operationImportRunId,
    ): void {
        if ($validatedRowsChunk === []) {
            return;
        }

        $rowsToPersist = array_column($validatedRowsChunk, 'row');

        $persistStart = microtime(true);
        $chunkBreakdown = $this->operationImportRowPersister->persistMany($rowsToPersist);
        $persistElapsed += microtime(true) - $persistStart;
        $persistedRows += count($rowsToPersist);

        if ($operationImportRunId !== null) {
            $persistedLineNumbers = array_column($validatedRowsChunk, 'line_number');

            OperationImportStagingRow::query()
                ->where('operation_import_run_id', $operationImportRunId)
                ->whereIn('line_number', $persistedLineNumbers)
                ->update([
                    'status' => OperationImportStagingRow::STATUS_PERSISTED,
                    'processed_at' => now(),
                    'error_message' => null,
                ]);
        }

        foreach ($chunkBreakdown as $metric => $elapsed) {
            $persistBreakdown[$metric] += $elapsed;
        }

        $validatedRowsChunk = [];
    }

    /**
     * @param  array<string,int>  $errorSummary
     */
    private function appendErrorSummary(array &$errorSummary, string $message): void
    {
        $errorSummary[$message] = ($errorSummary[$message] ?? 0) + 1;
    }

    /**
     * @param  array<string, string>|null  $rowPayload
     */
    private function recordRowError(
        ?int $operationImportRunId,
        ?int $lineNumber,
        string $message,
        ?array $rowPayload,
    ): void {
        if ($operationImportRunId === null) {
            return;
        }

        OperationImportRunError::query()->create([
            'operation_import_run_id' => $operationImportRunId,
            'line_number' => $lineNumber,
            'message' => $message,
            'row_payload' => $rowPayload,
        ]);
    }

    /**
     * @param  array<string, string>  $rowPayload
     */
    private function upsertStagingRow(
        ?int $operationImportRunId,
        int $lineNumber,
        array $rowPayload,
        string $status,
        ?string $errorMessage = null,
        bool $processedAtNow = false,
    ): void {
        if ($operationImportRunId === null) {
            return;
        }

        OperationImportStagingRow::query()->updateOrCreate(
            [
                'operation_import_run_id' => $operationImportRunId,
                'line_number' => $lineNumber,
            ],
            [
                'row_payload' => $rowPayload,
                'status' => $status,
                'error_message' => $errorMessage,
                'processed_at' => $processedAtNow ? now() : null,
            ],
        );
    }
}
