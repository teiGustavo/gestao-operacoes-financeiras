<?php

declare(strict_types=1);

use App\Infrastructure\Import\Jobs\FinalizeOperationCsvImportRunJob;
use App\Infrastructure\Import\Jobs\ProcessOperationCsvImportChunkJob;
use App\Infrastructure\Import\Jobs\ProcessOperationCsvImportJob;
use App\Infrastructure\Import\OperationCsvImporter;
use App\Models\Client;
use App\Models\Installment;
use App\Models\Operation;
use App\Models\OperationImportRun;
use App\Models\OperationImportRunChunk;
use App\Models\OperationImportRunError;
use App\Models\OperationImportStagingRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

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

it('queues a csv import run after validating the header', function () {
    Queue::fake();

    $csvPath = createImportCsv([
        baseImportRow(),
    ]);

    $this->artisan('operations:import', ['file' => $csvPath])
        ->expectsOutputToContain('Importacao enfileirada com sucesso')
        ->assertSuccessful();

    $run = OperationImportRun::query()->sole();

    Queue::assertPushed(ProcessOperationCsvImportJob::class, function (ProcessOperationCsvImportJob $job) use ($run): bool {
        return $job->operationImportRunId === $run->id;
    });

    expect($run->status)->toBe(OperationImportRun::STATUS_PENDING)
        ->and($run->queued_at)->not->toBeNull()
        ->and($run->file_path)->toBe($csvPath);
});

it('stores requested user id when queuing a csv import run', function () {
    Queue::fake();

    $user = User::factory()->create();
    $csvPath = createImportCsv([
        baseImportRow(),
    ]);

    $this->artisan('operations:import', [
        'file' => $csvPath,
        '--requested-by-user-id' => (string) $user->id,
    ])->assertSuccessful();

    $run = OperationImportRun::query()->sole();

    expect($run->requested_by_user_id)->toBe($user->id);
});

it('fails when requested user id is invalid', function () {
    Queue::fake();

    $csvPath = createImportCsv([
        baseImportRow(),
    ]);

    $this->artisan('operations:import', [
        'file' => $csvPath,
        '--requested-by-user-id' => 'abc',
    ])
        ->assertFailed()
        ->expectsOutputToContain('requested-by-user-id invalido');

    Queue::assertNothingPushed();
});

it('fails when requested user does not exist', function () {
    Queue::fake();

    $csvPath = createImportCsv([
        baseImportRow(),
    ]);

    $this->artisan('operations:import', [
        'file' => $csvPath,
        '--requested-by-user-id' => '999999',
    ])
        ->assertFailed()
        ->expectsOutputToContain('usuario solicitante 999999 nao encontrado');

    Queue::assertNothingPushed();
});

it('fails synchronously when header is invalid and does not enqueue run', function () {
    Queue::fake();

    $csvPath = createImportCsv([
        baseImportRow(),
    ], ['nome_completo', ...array_slice(RF02_CSV_HEADERS, 1)]);

    $this->artisan('operations:import', ['file' => $csvPath])
        ->assertFailed()
        ->expectsOutputToContain('cabecalho invalido');

    Queue::assertNothingPushed();
    expect(OperationImportRun::query()->count())->toBe(0);
});

it('processes a queued import run and persists valid rows', function () {
    $csvPath = createImportCsv([
        baseImportRow(),
    ]);

    $run = OperationImportRun::query()->create([
        'file_path' => $csvPath,
        'status' => OperationImportRun::STATUS_PENDING,
        'queued_at' => now(),
    ]);

    processImportRunPipeline($run->id);

    $run->refresh();

    expect($run->status)->toBe(OperationImportRun::STATUS_COMPLETED)
        ->and($run->total_rows)->toBe(1)
        ->and($run->imported_rows)->toBe(1)
        ->and($run->rejected_rows)->toBe(0)
        ->and($run->finished_at)->not->toBeNull()
        ->and(Client::query()->count())->toBe(1)
        ->and(Operation::query()->count())->toBe(1)
        ->and(Installment::query()->count())->toBe(3);
});

it('keeps importing when non-header row errors happen and records a summary in run', function () {
    $invalidRow = baseImportRow();
    $invalidRow['produto'] = 'OUTRO';

    $csvPath = createImportCsv([
        baseImportRow(),
        $invalidRow,
    ]);

    $run = OperationImportRun::query()->create([
        'file_path' => $csvPath,
        'status' => OperationImportRun::STATUS_PENDING,
        'queued_at' => now(),
    ]);

    processImportRunPipeline($run->id);

    $run->refresh();

    expect($run->status)->toBe(OperationImportRun::STATUS_COMPLETED_WITH_ERRORS)
        ->and($run->total_rows)->toBe(2)
        ->and($run->imported_rows)->toBe(1)
        ->and($run->rejected_rows)->toBe(1)
        ->and($run->error_summary)->toBeArray()
        ->and($run->error_summary)->not->toBe([])
        ->and(array_key_first($run->error_summary))->toContain('produto: Mapeamento invalido')
        ->and(Operation::query()->count())->toBe(1);

    $runErrors = OperationImportRunError::query()
        ->where('operation_import_run_id', $run->id)
        ->get();

    $stagingRows = OperationImportStagingRow::query()
        ->where('operation_import_run_id', $run->id)
        ->orderBy('line_number')
        ->get();

    expect($runErrors)->toHaveCount(1)
        ->and($runErrors->first()?->line_number)->toBe(3)
        ->and($runErrors->first()?->message)->toContain('produto: Mapeamento invalido')
        ->and($stagingRows)->toHaveCount(2)
        ->and($stagingRows[0]->status)->toBe(OperationImportStagingRow::STATUS_PERSISTED)
        ->and($stagingRows[1]->status)->toBe(OperationImportStagingRow::STATUS_REJECTED);
});

it('captures malformed csv structure as rejected rows summary in run', function () {
    $headers = implode(',', RF02_CSV_HEADERS);
    $validRow = implode(',', baseImportRow());
    $malformedRow = baseImportRow()
            |> array_values(...)
            |> (fn ($x) => array_slice($x, 0, 18))
            |> (fn ($x) => implode(',', $x));

    $csvPath = tempnam(sys_get_temp_dir(), 'rf02_import_');
    file_put_contents($csvPath, $headers."\n".$validRow."\n".$malformedRow."\n");

    $run = OperationImportRun::query()->create([
        'file_path' => $csvPath,
        'status' => OperationImportRun::STATUS_PENDING,
        'queued_at' => now(),
    ]);

    processImportRunPipeline($run->id);

    $run->refresh();

    expect($run->status)->toBe(OperationImportRun::STATUS_COMPLETED_WITH_ERRORS)
        ->and($run->imported_rows)->toBe(1)
        ->and($run->rejected_rows)->toBe(1)
        ->and(array_key_first($run->error_summary))->toContain('formato csv invalido');

    $runErrors = OperationImportRunError::query()
        ->where('operation_import_run_id', $run->id)
        ->get();

    $stagingRows = OperationImportStagingRow::query()
        ->where('operation_import_run_id', $run->id)
        ->get();

    expect($runErrors)->toHaveCount(1)
        ->and($runErrors->first()?->line_number)->toBeNull()
        ->and($runErrors->first()?->message)->toContain('formato csv invalido')
        ->and($stagingRows)->toHaveCount(1)
        ->and($stagingRows->first()?->status)->toBe(OperationImportStagingRow::STATUS_PERSISTED);

    @unlink($csvPath);
});

it('does not reprocess a run that is already processing', function () {
    $csvPath = createImportCsv([
        baseImportRow(),
    ]);

    $run = OperationImportRun::query()->create([
        'file_path' => $csvPath,
        'status' => OperationImportRun::STATUS_PROCESSING,
        'queued_at' => now(),
        'started_at' => now(),
    ]);

    $job = new ProcessOperationCsvImportJob($run->id);
    $job->handle(app(OperationCsvImporter::class));

    $run->refresh();

    expect($run->status)->toBe(OperationImportRun::STATUS_PROCESSING)
        ->and(Operation::query()->count())->toBe(0)
        ->and(Installment::query()->count())->toBe(0);
});

it('splits large imports into workers of ten thousand data rows', function () {
    $rows = [];

    for ($index = 0; $index < 10_001; $index++) {
        $row = baseImportRow();
        $row['cpf'] = str_pad((string) (10_000_000_000_00 + $index), 14, '0', STR_PAD_LEFT);
        $row['email'] = 'import-'.$index.'@example.com';
        $rows[] = $row;
    }

    $csvPath = createImportCsv($rows);

    $run = OperationImportRun::query()->create([
        'file_path' => $csvPath,
        'status' => OperationImportRun::STATUS_PENDING,
        'queued_at' => now(),
    ]);

    $job = new ProcessOperationCsvImportJob($run->id);
    $job->handle(app(OperationCsvImporter::class));

    $chunks = OperationImportRunChunk::query()
        ->where('operation_import_run_id', $run->id)
        ->orderBy('chunk_index')
        ->get();

    expect($chunks)->toHaveCount(2)
        ->and($chunks[0]->start_line_number)->toBe(2)
        ->and($chunks[0]->end_line_number)->toBe(10_001)
        ->and($chunks[1]->start_line_number)->toBe(10_002)
        ->and($chunks[1]->end_line_number)->toBe(10_002);
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

function processImportRunPipeline(int $runId): void
{
    $importer = app(OperationCsvImporter::class);

    $job = new ProcessOperationCsvImportJob($runId);
    $job->handle($importer);

    $chunkIds = OperationImportRunChunk::query()
        ->where('operation_import_run_id', $runId)
        ->pluck('id');

    foreach ($chunkIds as $chunkId) {
        $chunkJob = new ProcessOperationCsvImportChunkJob((int) $chunkId);
        $chunkJob->handle($importer);
    }

    $finalizeJob = new FinalizeOperationCsvImportRunJob($runId);
    $finalizeJob->handle();
}
