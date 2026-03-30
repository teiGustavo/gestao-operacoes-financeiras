<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ExportOperationsReportRequest;
use App\Infrastructure\Report\Jobs\ProcessOperationCsvExportJob;
use App\Models\OperationReportRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class OperationReportCsvExportController extends Controller
{
    public function __invoke(ExportOperationsReportRequest $request): JsonResponse|RedirectResponse
    {
        /** @var array{status?: string, operation?: int, product?: string, agreement?: int, reference_date?: string} $validated */
        $validated = $request->validated();
        $filters = collect($validated)->except(['reference_date'])->all();
        $referenceDate = $validated['reference_date'] ?? now()->toDateString();

        $operationReportRun = OperationReportRun::query()->create([
            'status' => OperationReportRun::STATUS_PENDING,
            'requested_by_user_id' => $request->user()?->id,
            'filters' => $filters,
            'reference_date' => $referenceDate,
            'queued_at' => now(),
        ]);

        dispatch(new ProcessOperationCsvExportJob($operationReportRun->id));

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Exportacao de relatorio enfileirada com sucesso.',
                'data' => [
                    'run_id' => $operationReportRun->id,
                    'status' => $operationReportRun->status,
                ],
            ], 202);
        }

        return back()->with('status', 'Exportacao de relatorio enfileirada com sucesso. Voce sera notificado ao concluir.');
    }
}
