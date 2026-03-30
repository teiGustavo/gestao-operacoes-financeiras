<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Contracts;

use App\Infrastructure\Import\Data\ExtractedTabularData;

interface OperationImportDataExtractorInterface
{
    public function extract(string $filePath): ExtractedTabularData;
}
