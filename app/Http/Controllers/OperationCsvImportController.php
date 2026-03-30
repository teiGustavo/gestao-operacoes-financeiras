<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ImportOperationsCsvRequest;
use App\Infrastructure\Import\Jobs\ProcessOperationCsvImportJob;
use App\Infrastructure\Import\OperationCsvImporter;
use App\Models\OperationImportRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class OperationCsvImportController extends Controller
{
    public function __construct(private readonly OperationCsvImporter $operationCsvImporter) {}

    public function __invoke(ImportOperationsCsvRequest $request): JsonResponse|RedirectResponse
    {
        $uploadedFile = $request->file('csv_file');
        $storedFilePath = $uploadedFile->store('imports', 'local');
        $absoluteFilePath = Storage::disk('local')->path($storedFilePath);

        try {
            $this->operationCsvImporter->ensureHeaderIsValid($absoluteFilePath);

            $operationImportRun = OperationImportRun::query()->create([
                'file_path' => $absoluteFilePath,
                'status' => OperationImportRun::STATUS_PENDING,
                'requested_by_user_id' => $request->user()?->id,
                'queued_at' => now(),
            ]);

            dispatch(new ProcessOperationCsvImportJob($operationImportRun->id));
        } catch (InvalidArgumentException $invalidArgumentException) {
            Storage::disk('local')->delete($storedFilePath);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Falha ao validar arquivo CSV.',
                    'errors' => [
                        ['code' => 'CSV_HEADER_INVALID', 'message' => $invalidArgumentException->getMessage()],
                    ],
                ], 422);
            }

            return back()->withErrors(['csv_file' => $invalidArgumentException->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Importacao de operacoes enfileirada com sucesso.',
                'data' => [
                    'run_id' => $operationImportRun->id,
                    'status' => $operationImportRun->status,
                ],
            ], 202);
        }

        return back()->with('status', 'Importacao de operacoes enfileirada com sucesso. Voce sera notificado ao concluir.');
    }
}
