<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Extractors;

use App\Infrastructure\Import\Contracts\OperationImportDataExtractorInterface;
use App\Infrastructure\Import\Data\ExtractedTabularData;
use Generator;
use InvalidArgumentException;

final class CsvOperationImportDataExtractor implements OperationImportDataExtractorInterface
{
    public function extract(string $filePath, ?int $startLineNumber = null, ?int $endLineNumber = null): ExtractedTabularData
    {
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

        $headers = array_map(static fn ($value) => trim((string) $value), $headerRow);

        $rows = $this->streamRows(
            handle: $handle,
            headers: $headers,
            startLineNumber: $startLineNumber,
            endLineNumber: $endLineNumber,
        );

        return new ExtractedTabularData(headers: $headers, rows: $rows);
    }

    /**
     * @param  resource  $handle
     * @param  list<string>  $headers
     * @param  int<2, max>|null  $startLineNumber
     * @param  int<2, max>|null  $endLineNumber
     * @return Generator<int, array{lineNumber:int,row:array<string,string>}>
     */
    private function streamRows($handle, array $headers, ?int $startLineNumber, ?int $endLineNumber): Generator
    {
        if ($startLineNumber !== null && $startLineNumber < 2) {
            throw new InvalidArgumentException('intervalo de linhas invalido');
        }

        if ($endLineNumber !== null && $endLineNumber < 2) {
            throw new InvalidArgumentException('intervalo de linhas invalido');
        }

        if ($startLineNumber !== null && $endLineNumber !== null && $startLineNumber > $endLineNumber) {
            throw new InvalidArgumentException('intervalo de linhas invalido');
        }

        $lineNumber = 1;

        try {
            while (($csvRow = fgetcsv($handle)) !== false) {
                $lineNumber++;

                if ($csvRow === [null] || $csvRow === []) {
                    continue;
                }

                if ($startLineNumber !== null && $lineNumber < $startLineNumber) {
                    continue;
                }

                if ($endLineNumber !== null && $lineNumber > $endLineNumber) {
                    break;
                }

                if (count($csvRow) !== count($headers)) {
                    throw new InvalidArgumentException('formato csv invalido: quantidade de colunas inconsistente');
                }

                $associativeRow = array_combine($headers, $csvRow);

                if ($associativeRow === false) {
                    throw new InvalidArgumentException('formato csv invalido');
                }

                /** @var array<string,string> $associativeRow */
                yield [
                    'lineNumber' => $lineNumber,
                    'row' => $associativeRow,
                ];
            }
        } finally {
            fclose($handle);
        }
    }
}
