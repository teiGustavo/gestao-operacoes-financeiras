<?php

declare(strict_types=1);

use App\Infrastructure\Import\Persistence\InstallmentsInsertChunkSizeResolver;

it('resolves installments insert chunk size using placeholder budget and target cap', function () {
    $resolver = new InstallmentsInsertChunkSizeResolver;

    expect($resolver->resolve())->toBe(2000);
});
