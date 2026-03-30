<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Data;

final readonly class ExtractedTabularData
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<array{lineNumber:int,row:array<string,string>}>  $rows
     */
    public function __construct(
        public array $headers,
        public iterable $rows,
    ) {}
}
