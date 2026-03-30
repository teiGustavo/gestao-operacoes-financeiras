<?php

declare(strict_types=1);

use App\Infrastructure\Import\Jobs\ProcessOperationCsvImportJob;
use App\Models\OperationImportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

const UI_IMPORT_HEADERS = [
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

it('redirects guests from csv import ui endpoint', function () {
    $file = uploadedCsvForUiImport([baseRowForUiImport()]);

    $this->post(route('operations.import.csv'), [
        'csv_file' => $file,
    ])->assertRedirect('/login');
});

it('queues csv import from ui for authenticated users', function () {
    Queue::fake();

    $user = User::factory()->create();
    $file = uploadedCsvForUiImport([baseRowForUiImport()]);

    $this->actingAs($user)
        ->post(route('operations.import.csv'), [
            'csv_file' => $file,
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Importacao de operacoes enfileirada com sucesso. Voce sera notificado ao concluir.');

    $run = OperationImportRun::query()->sole();

    expect($run->status)->toBe(OperationImportRun::STATUS_PENDING)
        ->and($run->requested_by_user_id)->toBe($user->id)
        ->and($run->queued_at)->not->toBeNull()
        ->and($run->file_path)->toContain('imports/');

    Queue::assertPushed(ProcessOperationCsvImportJob::class, function (ProcessOperationCsvImportJob $job) use ($run): bool {
        return $job->operationImportRunId === $run->id;
    });
});

it('rejects invalid csv header from ui and does not queue job', function () {
    Queue::fake();

    $user = User::factory()->create();
    $file = uploadedCsvForUiImport([
        baseRowForUiImport(),
    ], ['nome_completo', ...array_slice(UI_IMPORT_HEADERS, 1)]);

    $this->actingAs($user)
        ->from(route('operations.index'))
        ->post(route('operations.import.csv'), [
            'csv_file' => $file,
        ])
        ->assertRedirect(route('operations.index'))
        ->assertSessionHasErrors(['csv_file']);

    expect(OperationImportRun::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

/**
 * @param  list<array<string, string>>  $rows
 * @param  list<string>|null  $headers
 */
function uploadedCsvForUiImport(array $rows, ?array $headers = null): UploadedFile
{
    $headers ??= UI_IMPORT_HEADERS;
    $lines = [implode(',', $headers)];

    foreach ($rows as $row) {
        $lines[] = implode(',', array_map(
            static fn (string $column): string => $row[$column] ?? '',
            $headers,
        ));
    }

    return UploadedFile::fake()->createWithContent('operations.csv', implode("\n", $lines)."\n");
}

/**
 * @return array<string, string>
 */
function baseRowForUiImport(): array
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
