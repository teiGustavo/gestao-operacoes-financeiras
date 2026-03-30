<?php

declare(strict_types=1);

use App\Domain\Client\ClientGender;
use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\ProductType;
use App\Infrastructure\Import\Normalizers\DateValueNormalizer;
use App\Infrastructure\Import\Normalizers\DecimalValueNormalizer;
use App\Infrastructure\Import\Validators\OperationImportRowValidator;

const IMPORT_EXPECTED_HEADERS = [
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

it('normalizes and maps a valid row', function () {
    $validator = new OperationImportRowValidator(new DateValueNormalizer, new DecimalValueNormalizer);

    $normalizedRow = $validator->validateAndNormalizeRow(
        row: validImportRow(),
        lineNumber: 2,
        expectedHeaders: IMPORT_EXPECTED_HEADERS,
    );

    expect($normalizedRow['dt_nasc'])->toBe('1990-01-31')
        ->and($normalizedRow['status_id'])->toBe(OperationStatus::DRAFT->value)
        ->and($normalizedRow['produto'])->toBe(ProductType::PAYROLL_LOAN->value)
        ->and($normalizedRow['sexo'])->toBe(ClientGender::FEMALE->value)
        ->and($normalizedRow['valor_requerido'])->toBe('1000')
        ->and($normalizedRow['data_pagamento'])->toBe('');
});

it('fails when required value is missing', function () {
    $validator = new OperationImportRowValidator(new DateValueNormalizer, new DecimalValueNormalizer);

    $row = validImportRow();
    $row['nome'] = '';

    expect(fn () => $validator->validateAndNormalizeRow(
        row: $row,
        lineNumber: 2,
        expectedHeaders: IMPORT_EXPECTED_HEADERS,
    ))->toThrow(InvalidArgumentException::class, 'nome: valor obrigatorio ausente na linha 2');
});

it('fails when typed field is invalid', function () {
    $validator = new OperationImportRowValidator(new DateValueNormalizer, new DecimalValueNormalizer);

    $row = validImportRow();
    $row['valor_requerido'] = 'mil';

    expect(fn () => $validator->validateAndNormalizeRow(
        row: $row,
        lineNumber: 2,
        expectedHeaders: IMPORT_EXPECTED_HEADERS,
    ))->toThrow(InvalidArgumentException::class, 'valor_requerido: Tipo invalido na linha 2');
});

it('fails when mapped field has unsupported value', function () {
    $validator = new OperationImportRowValidator(new DateValueNormalizer, new DecimalValueNormalizer);

    $row = validImportRow();
    $row['produto'] = 'OUTRO';

    expect(fn () => $validator->validateAndNormalizeRow(
        row: $row,
        lineNumber: 2,
        expectedHeaders: IMPORT_EXPECTED_HEADERS,
    ))->toThrow(InvalidArgumentException::class, 'produto: Mapeamento invalido na linha 2');
});

/**
 * @return array<string,string>
 */
function validImportRow(): array
{
    return [
        'nome' => 'Ana Costa',
        'cpf' => '390.533.447-05',
        'dt_nasc' => '31/1/1990',
        'sexo' => 'F',
        'email' => 'ana@example.com',
        'valor_requerido' => '1000,00',
        'valor_desembolso' => '950,00',
        'total_juros' => '50,00',
        'status_id' => '1',
        'taxa_juros' => '1,50',
        'taxa_mora' => '1,00',
        'taxa_multa' => '2,00',
        'data_criacao' => '2026-05-01',
        'data_pagamento' => '',
        'produto' => 'CONSIGNADO',
        'conveniada_id' => '1',
        'quantidade_parcelas' => '3',
        'data_primeiro_vencimento' => '2026-06-01',
        'valor_parcela' => '350,00',
        'quantidade_parcelas_pagas' => '1',
    ];
}
