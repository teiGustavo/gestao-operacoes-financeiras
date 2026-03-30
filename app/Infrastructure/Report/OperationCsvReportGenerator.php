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

final readonly class OperationCsvReportGenerator
{
    public function __construct(
        private OperationReportCsvQuery $operationReportCsvQuery,
        private PresentValueCalculator $presentValueCalculator,
        private OperationRateResolver $operationRateResolver,
    ) {}

    /**
     * @param  array{status?: string, operation?: int, product?: string, agreement?: int}  $filters
     * @return array{output_file_path: string, total_rows: int}
     */
    public function generate(array $filters, DateTimeImmutable $referenceDate, int $runId): array
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        $disk->makeDirectory('reports');

        $relativePath = sprintf('reports/operations-report-run-%d.csv', $runId);
        $absolutePath = $disk->path($relativePath);

        $output = fopen($absolutePath, 'w');

        if ($output === false) {
            throw new \RuntimeException('Nao foi possivel criar arquivo de relatorio CSV.');
        }

        fputcsv($output, [
            'operation_code',
            'client_name',
            'cpf',
            'operation_value',
            'status',
            'product',
            'agreement',
            'present_value',
        ]);

        $totalRows = 0;

        foreach ($this->operationReportCsvQuery->cursor($filters) as $operation) {
            fputcsv($output, $this->csvRowFromOperation($operation, $referenceDate));
            $totalRows++;
        }

        fclose($output);

        return [
            'output_file_path' => $relativePath,
            'total_rows' => $totalRows,
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
