<?php

declare(strict_types=1);

use App\Infrastructure\Import\Persistence\OperationIdsByInstallmentsCountGrouper;

it('groups operation ids by quantidade_parcelas preserving input order inside each group', function () {
    $grouper = new OperationIdsByInstallmentsCountGrouper;

    $sourceRowsChunk = [
        ['quantidade_parcelas' => '3'],
        ['quantidade_parcelas' => '2'],
        ['quantidade_parcelas' => '3'],
        ['quantidade_parcelas' => '1'],
        ['quantidade_parcelas' => '2'],
    ];

    $operationIds = [11, 12, 13, 14, 15];

    $grouped = $grouper->group($sourceRowsChunk, $operationIds);

    expect($grouped)->toBe([
        3 => [11, 13],
        2 => [12, 15],
        1 => [14],
    ]);
});
