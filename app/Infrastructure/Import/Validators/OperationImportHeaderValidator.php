<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Validators;

use InvalidArgumentException;

final class OperationImportHeaderValidator
{
    /**
     * @param  list<string>  $headerRow
     * @param  list<string>  $expectedHeaders
     */
    public function validate(array $headerRow, array $expectedHeaders): void
    {
        $missingColumns = array_values(array_diff($expectedHeaders, $headerRow));
        $unexpectedColumns = array_values(array_diff($headerRow, $expectedHeaders));

        if (! $missingColumns && ! $unexpectedColumns) {
            return;
        }

        $messages = ['cabecalho invalido'];

        if ($missingColumns !== []) {
            $messages[] = 'colunas ausentes: '.implode(', ', $missingColumns);
        }

        if ($unexpectedColumns !== []) {
            $messages[] = 'colunas inesperadas: '.implode(', ', $unexpectedColumns);
        }

        throw new InvalidArgumentException(implode(' | ', $messages));
    }
}
