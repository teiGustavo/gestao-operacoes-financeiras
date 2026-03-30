<?php

declare(strict_types=1);

namespace App\Infrastructure\Report;

use App\Domain\Operation\Services\OperationRateResolver;
use App\Domain\Operation\Services\PresentValueCalculator;
use App\Infrastructure\Queries\Operation\OperationReportCsvQuery;
use App\Models\Operation;
use DateTimeImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;

final readonly class OperationCsvReportGenerator
{
    public function __construct(
        private OperationReportCsvQuery $operationReportCsvQuery,
        private PresentValueCalculator $presentValueCalculator,
        private OperationRateResolver $operationRateResolver,
    ) {}

    /**
     * @param  array{status?: string, operation?: int, product?: string, agreement?: int}  $filters
     * @return array{total_rows:int,chunks:list<array{chunk_index:int,start_operation_id:int,end_operation_id:int}>}
     */
    public function buildChunkPlan(array $filters, int $workerCount): array
    {
        if ($workerCount <= 0) {
            throw new \InvalidArgumentException('worker_count invalido');
        }

        $totalRows = $this->operationReportCsvQuery->count($filters);

        if ($totalRows === 0) {
            return [
                'total_rows' => 0,
                'chunks' => [],
            ];
        }

        $chunkSize = (int) ceil($totalRows / $workerCount);
        $rowsInChunk = 0;
        $chunkIndex = 1;
        $chunkStartOperationId = null;
        $chunkEndOperationId = null;
        $chunks = [];

        foreach ($this->operationIdCursor($filters) as $operationId) {
            if ($rowsInChunk === 0) {
                $chunkStartOperationId = $operationId;
            }

            $chunkEndOperationId = $operationId;
            $rowsInChunk++;

            if ($rowsInChunk >= $chunkSize) {
                $chunks[] = [
                    'chunk_index' => $chunkIndex,
                    'start_operation_id' => $chunkStartOperationId,
                    'end_operation_id' => $chunkEndOperationId,
                ];

                $chunkIndex++;
                $rowsInChunk = 0;
                $chunkStartOperationId = null;
                $chunkEndOperationId = null;
            }
        }

        if ($rowsInChunk > 0 && $chunkStartOperationId !== null && $chunkEndOperationId !== null) {
            $chunks[] = [
                'chunk_index' => $chunkIndex,
                'start_operation_id' => $chunkStartOperationId,
                'end_operation_id' => $chunkEndOperationId,
            ];
        }

        return [
            'total_rows' => $totalRows,
            'chunks' => $chunks,
        ];
    }

    /**
     * @param  array{status?: string, operation?: int, product?: string, agreement?: int}  $filters
     * @return array{output_file_path:string,total_rows:int,metrics:array{query:float,write:float,total:float}}
     */
    public function generateChunk(
        array $filters,
        DateTimeImmutable $referenceDate,
        int $runId,
        int $chunkIndex,
        int $startOperationId,
        int $endOperationId,
    ): array {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        $disk->makeDirectory('reports/chunks');

        $relativePath = sprintf('reports/chunks/operations-report-run-%d-chunk-%d.csv', $runId, $chunkIndex);
        $absolutePath = $disk->path($relativePath);

        $output = fopen($absolutePath, 'w');

        if ($output === false) {
            throw new \RuntimeException('Nao foi possivel criar arquivo de relatorio CSV.');
        }

        $totalStart = microtime(true);
        $queryElapsed = 0.0;
        $writeElapsed = 0.0;
        $totalRows = 0;

        $queryStart = microtime(true);
        $operations = $this->operationReportCsvQuery->cursorByIdRange(
            filters: $filters,
            startOperationId: $startOperationId,
            endOperationId: $endOperationId,
        );
        $queryElapsed += microtime(true) - $queryStart;

        foreach ($operations as $operation) {
            $writeStart = microtime(true);
            fputcsv($output, $this->csvRowFromOperation($operation, $referenceDate));
            $writeElapsed += microtime(true) - $writeStart;
            $totalRows++;
        }

        fclose($output);

        return [
            'output_file_path' => $relativePath,
            'total_rows' => $totalRows,
            'metrics' => [
                'query' => $queryElapsed,
                'write' => $writeElapsed,
                'total' => microtime(true) - $totalStart,
            ],
        ];
    }

    /**
     * @param  list<string>  $chunkRelativePaths
     * @return array{output_file_path:string,total_rows:int,metrics:array{merge:float,total:float}}
     */
    public function mergeChunkFiles(int $runId, array $chunkRelativePaths): array
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        $disk->makeDirectory('reports');

        $relativePath = sprintf('reports/operations-report-run-%d.csv', $runId);
        $absolutePath = $disk->path($relativePath);
        $totalStart = microtime(true);

        $output = fopen($absolutePath, 'w');

        if ($output === false) {
            throw new \RuntimeException('Nao foi possivel criar arquivo de relatorio CSV final.');
        }

        fputcsv($output, $this->csvHeaders());

        $mergeStart = microtime(true);
        $totalRows = 0;

        foreach ($chunkRelativePaths as $chunkRelativePath) {
            $chunkAbsolutePath = $disk->path($chunkRelativePath);
            $chunkHandle = fopen($chunkAbsolutePath, 'rb');

            if ($chunkHandle === false) {
                continue;
            }

            while (($chunkRow = fgetcsv($chunkHandle)) !== false) {
                fputcsv($output, $chunkRow);
                $totalRows++;
            }

            fclose($chunkHandle);
            $disk->delete($chunkRelativePath);
        }

        fclose($output);

        return [
            'output_file_path' => $relativePath,
            'total_rows' => $totalRows,
            'metrics' => [
                'merge' => microtime(true) - $mergeStart,
                'total' => microtime(true) - $totalStart,
            ],
        ];
    }

    /**
     * @param  array{status?: string, operation?: int, product?: string, agreement?: int}  $filters
     * @return LazyCollection<int, int>
     */
    private function operationIdCursor(array $filters): LazyCollection
    {
        return $this->operationReportCsvQuery->operationIdCursor($filters);
    }

    /**
     * @return list<string>
     */
    private function csvHeaders(): array
    {
        return [
            'operation_code',
            'client_name',
            'cpf',
            'operation_value',
            'status',
            'product',
            'agreement',
            'present_value',
        ];
    }

    /**
     * @return list<string>
     */
    private function csvRowFromOperation(Operation $operation, DateTimeImmutable $referenceDate): array
    {
        $operationRate = $this->operationRateResolver->resolveFromTotalInterest(
            requestedValue: (float) $operation->requested_value,
            totalInterest: (float) $operation->total_interest,
        );

        $presentValue = 0.0;

        foreach ($operation->installments as $installment) {
            $presentValue += $this->presentValueCalculator->calculate(
                installmentValue: (float) $installment->value,
                dueDate: $installment->due_date,
                referenceDate: $referenceDate,
                lateFeeRate: max(0.0, ((float) $operation->late_fee_rate) / 100),
                lateInterestRate: max(0.0, ((float) $operation->late_interest_rate) / 100),
                operationRate: $operationRate,
            );
        }

        return [
            (string) $operation->id,
            (string) ($operation->client?->name ?? ''),
            (string) ($operation->client?->cpf ?? ''),
            number_format((float) $operation->requested_value, 2, '.', ''),
            $operation->status->label(),
            $operation->product_type->label(),
            (string) ($operation->agreement?->name ?? ''),
            number_format(round($presentValue, 2, \PHP_ROUND_HALF_EVEN), 2, '.', ''),
        ];
    }
}
