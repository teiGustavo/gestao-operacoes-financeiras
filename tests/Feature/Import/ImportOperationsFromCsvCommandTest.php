<?php

declare(strict_types=1);

use App\Domain\Client\ClientGender;
use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\ProductType;
use App\Models\Agreement;
use App\Models\Client;
use App\Models\Installment;
use App\Models\Operation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const RF02_CSV_HEADERS = [
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

it('imports a valid csv row and maps data into client, operation and installments', function () {
    Agreement::query()->create([
        'name' => 'Prefeitura de Leopoldina',
    ]);

    $csvPath = createImportCsv([
        [
            'nome' => 'Ana Costa',
            'cpf' => 'EXP3BTMYeodP9U',
            'dt_nasc' => '1990-01-01',
            'sexo' => 'F',
            'email' => 'ana@example.com',
            'valor_requerido' => '1000.00',
            'valor_desembolso' => '950.00',
            'total_juros' => '50.00',
            'status_id' => '1',
            'taxa_juros' => '1.50',
            'taxa_mora' => '1.00',
            'taxa_multa' => '2.00',
            'data_criacao' => '2026-05-01',
            'data_pagamento' => '',
            'produto' => 'CONSIGNADO',
            'conveniada_id' => '1',
            'quantidade_parcelas' => '3',
            'data_primeiro_vencimento' => '2026-06-01',
            'valor_parcela' => '350.00',
            'quantidade_parcelas_pagas' => '1',
        ],
    ]);

    $this->artisan('operations:import', ['file' => $csvPath])
        ->assertSuccessful();

    $client = Client::query()->sole();
    $operation = Operation::query()->sole();
    $installments = Installment::query()->orderBy('installment_number')->get();

    expect($client->name)->toBe('Ana Costa')
        ->and($client->cpf)->toBe('EXP3BTMYeodP9U')
        ->and($client->gender)->toBe(ClientGender::FEMALE)
        ->and($operation->client_id)->toBe($client->id)
        ->and($operation->agreement_id)->toBe(1)
        ->and($operation->requested_value)->toBe(1000.0)
        ->and($operation->disbursement_value)->toBe(950.0)
        ->and($operation->status)->toBe(OperationStatus::DRAFT)
        ->and($operation->product_type)->toBe(ProductType::PAYROLL_LOAN)
        ->and($operation->installments_count)->toBe(3)
        ->and($operation->paid_installments_count)->toBe(1)
        ->and($installments)->toHaveCount(3)
        ->and($installments[0]->installment_number)->toBe(1)
        ->and($installments[1]->due_date->format('Y-m-d'))->toBe('2026-07-01')
        ->and($installments[2]->due_date->format('Y-m-d'))->toBe('2026-08-01');
});

it('imports many rows keeping operation and installment mapping consistent across bulk inserts', function () {
    $rows = [];

    for ($index = 1; $index <= 1200; $index++) {
        $installmentsCount = ($index % 4) + 1;
        $paidInstallmentsCount = min($installmentsCount, $index % 2);
        $installmentValue = number_format(100 + ($index / 100), 2, '.', '');

        $rows[] = [
            ...baseImportRow(),
            'nome' => 'Cliente '.$index,
            'cpf' => str_pad((string) $index, 14, '0', STR_PAD_LEFT),
            'email' => 'cliente'.$index.'@example.com',
            'conveniada_id' => '',
            'quantidade_parcelas' => (string) $installmentsCount,
            'quantidade_parcelas_pagas' => (string) $paidInstallmentsCount,
            'valor_parcela' => $installmentValue,
            'valor_requerido' => number_format($installmentsCount * (float) $installmentValue, 2, '.', ''),
            'valor_desembolso' => number_format(($installmentsCount * (float) $installmentValue) - 5, 2, '.', ''),
            'total_juros' => '5.00',
        ];
    }

    $csvPath = createImportCsv($rows);

    $this->artisan('operations:import', ['file' => $csvPath])
        ->assertSuccessful();

    expect(Client::query()->count())->toBe(1200)
        ->and(Operation::query()->count())->toBe(1200)
        ->and(Installment::query()->count())->toBe(3000)
        ->and(Agreement::query()->where('name', 'Conveniada Gerada Automaticamente')->exists())->toBeTrue();

    $sampleOperation = Operation::query()->where('installment_value', 111.11)->firstOrFail();
    $sampleInstallments = Installment::query()
        ->where('operation_id', $sampleOperation->id)
        ->orderBy('installment_number')
        ->get();

    expect($sampleOperation->installments_count)->toBe(4)
        ->and($sampleOperation->paid_installments_count)->toBe(1)
        ->and($sampleInstallments)->toHaveCount(4)
        ->and($sampleInstallments->pluck('value')->map(static fn (float $value): float => round($value, 2))->unique()->values()->all())
        ->toBe([111.11]);
});

it('fails when csv header is different from the expected format', function () {
    $csvPath = createImportCsv([
        baseImportRow(),
    ], ['nome_completo', ...array_slice(RF02_CSV_HEADERS, 1)]);

    $this->artisan('operations:import', ['file' => $csvPath])
        ->assertFailed()
        ->expectsOutputToContain('cabecalho invalido');
});

it('fails when csv row has malformed column count', function () {
    $headers = implode(',', RF02_CSV_HEADERS);
    $malformedRow = implode(',', array_slice(array_values(baseImportRow()), 0, 18));

    $csvPath = tempnam(sys_get_temp_dir(), 'rf02_import_');
    file_put_contents($csvPath, $headers."\n".$malformedRow."\n");

    $this->artisan('operations:import', ['file' => $csvPath])
        ->assertFailed()
        ->expectsOutputToContain('formato csv invalido');
});

it('fails when required header column is missing', function (string $requiredColumn) {
    $headers = array_values(array_filter(
        RF02_CSV_HEADERS,
        fn (string $column): bool => $column !== $requiredColumn,
    ));

    $row = baseImportRow();
    unset($row[$requiredColumn]);

    $csvPath = createImportCsv([$row], $headers);

    $this->artisan('operations:import', ['file' => $csvPath])
        ->assertFailed()
        ->expectsOutputToContain($requiredColumn);
})->with([
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
]);

it('fails when a typed field receives an invalid value', function (string $column, string $invalidValue) {
    $row = baseImportRow();
    $row[$column] = $invalidValue;

    $csvPath = createImportCsv([$row]);

    $this->artisan('operations:import', ['file' => $csvPath])
        ->assertFailed()
        ->expectsOutputToContain($column);
})->with([
    'dt_nasc must be date' => ['dt_nasc', '1990-31-12'],
    'valor_requerido must be decimal' => ['valor_requerido', 'mil'],
    'valor_desembolso must be decimal' => ['valor_desembolso', 'abc'],
    'total_juros must be decimal' => ['total_juros', 'xpto'],
    'status_id must be integer' => ['status_id', 'draft'],
    'taxa_juros must be decimal' => ['taxa_juros', 'um'],
    'taxa_mora must be decimal' => ['taxa_mora', 'mora'],
    'taxa_multa must be decimal' => ['taxa_multa', 'multa'],
    'data_criacao must be date' => ['data_criacao', '2026-25-01'],
    'data_pagamento must be nullable date' => ['data_pagamento', '2026-31-13'],
    'conveniada_id must be integer' => ['conveniada_id', 'A1'],
    'quantidade_parcelas must be integer' => ['quantidade_parcelas', 'dez'],
    'data_primeiro_vencimento must be date' => ['data_primeiro_vencimento', '06-01-2026'],
    'valor_parcela must be decimal' => ['valor_parcela', 'parcela'],
    'quantidade_parcelas_pagas must be integer' => ['quantidade_parcelas_pagas', 'um'],
]);

it('fails when sexo value is outside allowed mapping', function () {
    $row = baseImportRow();
    $row['sexo'] = 'X';

    $csvPath = createImportCsv([$row]);

    $this->artisan('operations:import', ['file' => $csvPath])
        ->assertFailed()
        ->expectsOutputToContain('sexo');
});

it('fails when produto value is outside allowed mapping', function () {
    $row = baseImportRow();
    $row['produto'] = 'OUTRO';

    $csvPath = createImportCsv([$row]);

    $this->artisan('operations:import', ['file' => $csvPath])
        ->assertFailed()
        ->expectsOutputToContain('produto');
});

it('fails when status_id is outside allowed mapping', function () {
    $row = baseImportRow();
    $row['status_id'] = '99';

    $csvPath = createImportCsv([$row]);

    $this->artisan('operations:import', ['file' => $csvPath])
        ->assertFailed()
        ->expectsOutputToContain('status_id');
});

/**
 * @param  list<array<string, string>>  $rows
 * @param  list<string>|null  $headers
 */
function createImportCsv(array $rows, ?array $headers = null): string
{
    $headers ??= RF02_CSV_HEADERS;

    $csvPath = tempnam(sys_get_temp_dir(), 'rf02_import_');

    $lines = [implode(',', $headers)];

    foreach ($rows as $row) {
        $lines[] = implode(',', array_map(
            fn (string $column): string => $row[$column] ?? '',
            $headers,
        ));
    }

    file_put_contents($csvPath, implode("\n", $lines)."\n");

    return $csvPath;
}

/**
 * @return array<string, string>
 */
function baseImportRow(): array
{
    return [
        'nome' => 'Ana Costa',
        'cpf' => 'EXP3BTMYeodP9U',
        'dt_nasc' => '1990-01-01',
        'sexo' => 'F',
        'email' => 'ana@example.com',
        'valor_requerido' => '1000.00',
        'valor_desembolso' => '950.00',
        'total_juros' => '50.00',
        'status_id' => '1',
        'taxa_juros' => '1.50',
        'taxa_mora' => '1.00',
        'taxa_multa' => '2.00',
        'data_criacao' => '2026-05-01',
        'data_pagamento' => '',
        'produto' => 'CONSIGNADO',
        'conveniada_id' => '1',
        'quantidade_parcelas' => '3',
        'data_primeiro_vencimento' => '2026-06-01',
        'valor_parcela' => '350.00',
        'quantidade_parcelas_pagas' => '1',
    ];
}
