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
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OperationCsvImporter
{
    private const int DEFAULT_PERSIST_ROWS_CHUNK_SIZE = 2_000;

    private const int MIN_PERSIST_ROWS_CHUNK_SIZE = 500;

    private const int MAX_PERSIST_ROWS_CHUNK_SIZE = 10_000;

    private const int STAGING_ROWS_UPSERT_CHUNK_SIZE = 2_000;

    private const int ERROR_ROWS_INSERT_CHUNK_SIZE = 1_000;

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
        ?int $startByteOffset = null,
        bool $shouldValidateHeader = true,
    ): array {
        $totalStart = microtime(true);

        $extractStart = microtime(true);
        $extractedData = $this->operationImportDataExtractor->extract(
            filePath: $filePath,
            startLineNumber: $startLineNumber,
            endLineNumber: $endLineNumber,
            startByteOffset: $startByteOffset,
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
            $persistRowsChunkSize = $this->resolvePersistRowsChunkSize(
                startLineNumber: $startLineNumber,
                endLineNumber: $endLineNumber,
            );
            $validatedRowsChunk = [];
            $stagingRowsBuffer = [];
            $errorRowsBuffer = [];

            try {
                foreach ($extractedData->rows as $extractedRow) {
                    $totalRows++;
                    $lineNumber = $extractedRow['lineNumber'];

                    try {
                        $rowValidationStart = microtime(true);
                        $validatedRowsChunk[] = [
                            'line_number' => $lineNumber,
                            'row' => $this->validateAndNormalizeRow(
                                row: $extractedRow['row'],
                                lineNumber: $lineNumber,
                            ),
                        ];

                        $this->enqueueStagingRowUpsert(
                            stagingRowsBuffer: $stagingRowsBuffer,
                            lineNumber: $lineNumber,
                            rowPayload: $extractedRow['row'],
                            status: OperationImportStagingRow::STATUS_PENDING,
                        );

                        $this->flushStagingRowsBufferIfNeeded(
                            operationImportRunId: $operationImportRunId,
                            stagingRowsBuffer: $stagingRowsBuffer,
                        );

                        $rowValidationElapsed += microtime(true) - $rowValidationStart;
                    } catch (InvalidArgumentException $invalidArgumentException) {
                        $rejectedRows++;
                        $this->appendErrorSummary($errorSummary, $invalidArgumentException->getMessage());
                        $this->enqueueRowError(
                            errorRowsBuffer: $errorRowsBuffer,
                            operationImportRunId: $operationImportRunId,
                            lineNumber: $lineNumber,
                            message: $invalidArgumentException->getMessage(),
                            rowPayload: $extractedRow['row'],
                        );
                        $this->flushErrorRowsBufferIfNeeded(
                            operationImportRunId: $operationImportRunId,
                            errorRowsBuffer: $errorRowsBuffer,
                        );

                        $this->enqueueStagingRowUpsert(
                            stagingRowsBuffer: $stagingRowsBuffer,
                            lineNumber: $lineNumber,
                            rowPayload: $extractedRow['row'],
                            status: OperationImportStagingRow::STATUS_REJECTED,
                            errorMessage: $invalidArgumentException->getMessage(),
                            processedAtNow: true,
                        );

                        $this->flushStagingRowsBufferIfNeeded(
                            operationImportRunId: $operationImportRunId,
                            stagingRowsBuffer: $stagingRowsBuffer,
                        );

                        continue;
                    }

                    if (count($validatedRowsChunk) >= $persistRowsChunkSize) {
                        $this->flushStagingRowsBuffer(
                            operationImportRunId: $operationImportRunId,
                            stagingRowsBuffer: $stagingRowsBuffer,
                        );

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
                $this->enqueueRowError(
                    errorRowsBuffer: $errorRowsBuffer,
                    operationImportRunId: $operationImportRunId,
                    lineNumber: null,
                    message: $invalidArgumentException->getMessage(),
                    rowPayload: null,
                );
            }

            $this->flushStagingRowsBuffer(
                operationImportRunId: $operationImportRunId,
                stagingRowsBuffer: $stagingRowsBuffer,
            );

            $this->persistValidatedRowsChunk(
                validatedRowsChunk: $validatedRowsChunk,
                persistElapsed: $persistElapsed,
                persistedRows: $importedRows,
                persistBreakdown: $persistBreakdown,
                operationImportRunId: $operationImportRunId,
            );

            $this->flushErrorRowsBuffer(
                operationImportRunId: $operationImportRunId,
                errorRowsBuffer: $errorRowsBuffer,
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

    /**
     * @return array{total_rows:int,chunks:list<array{chunk_index:int,start_line_number:int,end_line_number:int,start_byte_offset:int}>}
     */
    public function buildChunkPlan(string $filePath, int $workerCount): array
    {
        if ($workerCount <= 0) {
            throw new InvalidArgumentException('worker_count invalido');
        }

        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new InvalidArgumentException('arquivo csv nao encontrado');
        }

        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            throw new InvalidArgumentException('arquivo csv nao pode ser lido');
        }

        $headerRow = fgetcsv($handle);

        if (! is_array($headerRow)) {
            fclose($handle);

            throw new InvalidArgumentException('formato csv invalido');
        }

        $lineNumber = 1;
        $rows = [];

        while (true) {
            $rowByteOffset = ftell($handle);

            if (! is_int($rowByteOffset)) {
                fclose($handle);

                throw new InvalidArgumentException('formato csv invalido');
            }

            $csvRow = fgetcsv($handle);

            if ($csvRow === false) {
                break;
            }

            $lineNumber++;

            if ($csvRow === [null] || $csvRow === []) {
                continue;
            }

            $rows[] = [
                'line_number' => $lineNumber,
                'byte_offset' => $rowByteOffset,
            ];
        }

        fclose($handle);

        $totalRows = count($rows);

        if ($totalRows === 0) {
            return [
                'total_rows' => 0,
                'chunks' => [],
            ];
        }

        $chunkSize = (int) ceil($totalRows / $workerCount);
        $chunks = [];
        $chunkIndex = 1;

        for ($startIndex = 0; $startIndex < $totalRows; $startIndex += $chunkSize) {
            $endIndex = min($startIndex + $chunkSize - 1, $totalRows - 1);
            $startRow = $rows[$startIndex];
            $endRow = $rows[$endIndex];

            $chunks[] = [
                'chunk_index' => $chunkIndex,
                'start_line_number' => $startRow['line_number'],
                'end_line_number' => $endRow['line_number'],
                'start_byte_offset' => $startRow['byte_offset'],
            ];

            $chunkIndex++;
        }

        return [
            'total_rows' => $totalRows,
            'chunks' => $chunks,
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
        $chunkBreakdown = DB::transaction(function () use ($rowsToPersist, $validatedRowsChunk, $operationImportRunId): array {
            $chunkBreakdown = $this->operationImportRowPersister->persistMany($rowsToPersist);

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

            return $chunkBreakdown;
        }, 5);
        $persistElapsed += microtime(true) - $persistStart;
        $persistedRows += count($rowsToPersist);

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
    private function enqueueRowError(
        array &$errorRowsBuffer,
        ?int $operationImportRunId,
        ?int $lineNumber,
        string $message,
        ?array $rowPayload,
    ): void {
        if ($operationImportRunId === null) {
            return;
        }

        $errorRowsBuffer[] = [
            'operation_import_run_id' => $operationImportRunId,
            'line_number' => $lineNumber,
            'message' => $message,
            'row_payload' => $rowPayload,
        ];
    }

    /**
     * @param  list<array{operation_import_run_id: int, line_number: int|null, message: string, row_payload: array<string, string>|null}>  $errorRowsBuffer
     */
    private function flushErrorRowsBufferIfNeeded(?int $operationImportRunId, array &$errorRowsBuffer): void
    {
        if (count($errorRowsBuffer) < self::ERROR_ROWS_INSERT_CHUNK_SIZE) {
            return;
        }

        $this->flushErrorRowsBuffer(
            operationImportRunId: $operationImportRunId,
            errorRowsBuffer: $errorRowsBuffer,
        );
    }

    /**
     * @param  list<array{operation_import_run_id: int, line_number: int|null, message: string, row_payload: array<string, string>|null}>  $errorRowsBuffer
     */
    private function flushErrorRowsBuffer(?int $operationImportRunId, array &$errorRowsBuffer): void
    {
        if ($operationImportRunId === null || $errorRowsBuffer === []) {
            $errorRowsBuffer = [];

            return;
        }

        $now = now();

        $payload = array_map(static function (array $errorRow) use ($now): array {
            return [
                'operation_import_run_id' => $errorRow['operation_import_run_id'],
                'line_number' => $errorRow['line_number'],
                'message' => $errorRow['message'],
                'row_payload' => $errorRow['row_payload'] === null
                    ? null
                    : json_encode($errorRow['row_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $errorRowsBuffer);

        OperationImportRunError::query()->insert($payload);

        $errorRowsBuffer = [];
    }

    /**
     * @param  array<int, array{line_number: int, row_payload: array<string, string>, status: string, error_message: string|null, processed_at: string|null}>  $stagingRowsBuffer
     * @param  array<string, string>  $rowPayload
     */
    private function enqueueStagingRowUpsert(
        array &$stagingRowsBuffer,
        int $lineNumber,
        array $rowPayload,
        string $status,
        ?string $errorMessage = null,
        bool $processedAtNow = false,
    ): void {
        $stagingRowsBuffer[] = [
            'line_number' => $lineNumber,
            'row_payload' => $rowPayload,
            'status' => $status,
            'error_message' => $errorMessage,
            'processed_at' => $processedAtNow ? now()->toDateTimeString() : null,
        ];
    }

    /**
     * @param  array<int, array{line_number: int, row_payload: array<string, string>, status: string, error_message: string|null, processed_at: string|null}>  $stagingRowsBuffer
     */
    private function flushStagingRowsBufferIfNeeded(?int $operationImportRunId, array &$stagingRowsBuffer): void
    {
        if (count($stagingRowsBuffer) < self::STAGING_ROWS_UPSERT_CHUNK_SIZE) {
            return;
        }

        $this->flushStagingRowsBuffer(
            operationImportRunId: $operationImportRunId,
            stagingRowsBuffer: $stagingRowsBuffer,
        );
    }

    /**
     * @param  array<int, array{line_number: int, row_payload: array<string, string>, status: string, error_message: string|null, processed_at: string|null}>  $stagingRowsBuffer
     */
    private function flushStagingRowsBuffer(?int $operationImportRunId, array &$stagingRowsBuffer): void
    {
        if ($operationImportRunId === null || $stagingRowsBuffer === []) {
            $stagingRowsBuffer = [];

            return;
        }

        $now = now();
        $payload = array_map(
            function (array $row) use ($operationImportRunId, $now): array {
                return [
                    'operation_import_run_id' => $operationImportRunId,
                    'line_number' => $row['line_number'],
                    'row_payload' => json_encode($row['row_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'status' => $row['status'],
                    'error_message' => $row['error_message'],
                    'processed_at' => $row['processed_at'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            },
            $stagingRowsBuffer,
        );

        OperationImportStagingRow::query()->upsert(
            $payload,
            ['operation_import_run_id', 'line_number'],
            ['row_payload', 'status', 'error_message', 'processed_at', 'updated_at'],
        );

        $stagingRowsBuffer = [];
    }

    private function resolvePersistRowsChunkSize(?int $startLineNumber, ?int $endLineNumber): int
    {
        if ($startLineNumber === null || $endLineNumber === null || $endLineNumber < $startLineNumber) {
            return self::DEFAULT_PERSIST_ROWS_CHUNK_SIZE;
        }

        $rowsInScope = $endLineNumber - $startLineNumber + 1;
        $workers = max(1, (int) config('imports.parallel_workers', 4));
        $targetBatchesPerWorker = 3;
        $rawChunkSize = (int) ceil($rowsInScope / max(1, $workers * $targetBatchesPerWorker));

        return max(self::MIN_PERSIST_ROWS_CHUNK_SIZE, min(self::MAX_PERSIST_ROWS_CHUNK_SIZE, $rawChunkSize));
    }
}
