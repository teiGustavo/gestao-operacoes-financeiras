<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Extractors;

use App\Infrastructure\Import\Contracts\OperationImportDataExtractorInterface;
use App\Infrastructure\Import\Data\ExtractedTabularData;
use Generator;
use InvalidArgumentException;

final class CsvOperationImportDataExtractor implements OperationImportDataExtractorInterface
{
    public function extract(string $filePath): ExtractedTabularData
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

        $rows = $this->streamRows($handle, $headers);

        return new ExtractedTabularData(headers: $headers, rows: $rows);
    }

    /**
     * @param  resource  $handle
     * @param  list<string>  $headers
     * @return Generator<int, array{lineNumber:int,row:array<string,string>}>
     */
    private function streamRows($handle, array $headers): Generator
    {
        $lineNumber = 1;

        try {
            while (($csvRow = fgetcsv($handle)) !== false) {
                $lineNumber++;

                if ($csvRow === [null] || $csvRow === []) {
                    continue;
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
