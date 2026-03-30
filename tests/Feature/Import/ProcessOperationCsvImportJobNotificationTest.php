<?php

declare(strict_types=1);

use App\Infrastructure\Import\Jobs\ProcessOperationCsvImportJob;
use App\Infrastructure\Import\OperationCsvImporter;
use App\Models\OperationImportRun;
use App\Models\User;
use App\Notifications\OperationImportFinishedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('notifies requested user when import completes successfully', function () {
    Notification::fake();

    $user = User::factory()->create();
    $csvPath = createNotificationTestCsv([baseImportRowForNotificationTest()]);
    $run = createRunForNotificationTest($csvPath, $user->id);

    $job = new ProcessOperationCsvImportJob($run->id);
    $job->handle(app(OperationCsvImporter::class));

    $run->refresh();

    Notification::assertSentTo($user, OperationImportFinishedNotification::class, function (OperationImportFinishedNotification $notification) use ($run, $user): bool {
        return ($notification->toArray($user)['run_id'] ?? null) === $run->id;
    });

    $notification = Notification::sent($user, OperationImportFinishedNotification::class)->first();

    expect($run->status)->toBe(OperationImportRun::STATUS_COMPLETED)
        ->and($notification)->not->toBeNull()
        ->and($notification->toArray($user)['run_id'])->toBe($run->id)
        ->and($notification->toArray($user)['status'])->toBe(OperationImportRun::STATUS_COMPLETED);
});

it('notifies requested user when import completes with row errors', function () {
    Notification::fake();

    $user = User::factory()->create();
    $invalidRow = baseImportRowForNotificationTest();
    $invalidRow['produto'] = 'OUTRO';
    $csvPath = createNotificationTestCsv([
        baseImportRowForNotificationTest(),
        $invalidRow,
    ]);
    $run = createRunForNotificationTest($csvPath, $user->id);

    $job = new ProcessOperationCsvImportJob($run->id);
    $job->handle(app(OperationCsvImporter::class));

    $run->refresh();

    Notification::assertSentTo($user, OperationImportFinishedNotification::class);

    $notification = Notification::sent($user, OperationImportFinishedNotification::class)->first();

    expect($run->status)->toBe(OperationImportRun::STATUS_COMPLETED_WITH_ERRORS)
        ->and($notification)->not->toBeNull()
        ->and($notification->toArray($user)['run_id'])->toBe($run->id)
        ->and($notification->toArray($user)['status'])->toBe(OperationImportRun::STATUS_COMPLETED_WITH_ERRORS)
        ->and($notification->toArray($user)['rejected_rows'])->toBe(1);
});

it('notifies requested user when import fails', function () {
    Notification::fake();

    $user = User::factory()->create();
    $run = createRunForNotificationTest('/tmp/inexistente.csv', $user->id);

    $job = new ProcessOperationCsvImportJob($run->id);

    try {
        $job->handle(app(OperationCsvImporter::class));
    } catch (Throwable) {
    }

    $run->refresh();

    Notification::assertSentTo($user, OperationImportFinishedNotification::class);

    $notification = Notification::sent($user, OperationImportFinishedNotification::class)->first();

    expect($run->status)->toBe(OperationImportRun::STATUS_FAILED)
        ->and($notification)->not->toBeNull()
        ->and($notification->toArray($user)['run_id'])->toBe($run->id)
        ->and($notification->toArray($user)['status'])->toBe(OperationImportRun::STATUS_FAILED)
        ->and($notification->toArray($user)['failure_message'])->not->toBeNull();
});

it('does not notify when run has no requested user', function () {
    Notification::fake();

    $csvPath = createNotificationTestCsv([baseImportRowForNotificationTest()]);
    $run = createRunForNotificationTest($csvPath, null);

    $job = new ProcessOperationCsvImportJob($run->id);
    $job->handle(app(OperationCsvImporter::class));

    Notification::assertNothingSent();
});

/**
 * @param  list<array<string, string>>  $rows
 */
function createNotificationTestCsv(array $rows): string
{
    $headers = [
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

    $csvPath = tempnam(sys_get_temp_dir(), 'rf02_notification_');
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
function baseImportRowForNotificationTest(): array
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

function createRunForNotificationTest(string $filePath, ?int $requestedByUserId): OperationImportRun
{
    return OperationImportRun::query()->create([
        'file_path' => $filePath,
        'status' => OperationImportRun::STATUS_PENDING,
        'requested_by_user_id' => $requestedByUserId,
        'queued_at' => now(),
    ]);
}
