<?php

declare(strict_types=1);

use App\Infrastructure\Import\Validators\OperationImportHeaderValidator;

const OPERATION_IMPORT_EXPECTED_HEADERS = [
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

it('accepts expected header list', function () {
    $validator = new OperationImportHeaderValidator;

    $validator->validate(OPERATION_IMPORT_EXPECTED_HEADERS, OPERATION_IMPORT_EXPECTED_HEADERS);

    expect(true)->toBeTrue();
});

it('fails when a required header column is missing', function () {
    $validator = new OperationImportHeaderValidator;

    $headers = array_values(array_filter(
        OPERATION_IMPORT_EXPECTED_HEADERS,
        static fn (string $column): bool => $column !== 'nome',
    ));

    expect(fn () => $validator->validate($headers, OPERATION_IMPORT_EXPECTED_HEADERS))
        ->toThrow(InvalidArgumentException::class, 'cabecalho invalido | colunas ausentes: nome');
});

it('fails when header has unexpected columns', function () {
    $validator = new OperationImportHeaderValidator;

    $headers = OPERATION_IMPORT_EXPECTED_HEADERS;
    $headers[0] = 'nome_completo';

    expect(fn () => $validator->validate($headers, OPERATION_IMPORT_EXPECTED_HEADERS))
        ->toThrow(InvalidArgumentException::class, 'colunas inesperadas: nome_completo');
});
