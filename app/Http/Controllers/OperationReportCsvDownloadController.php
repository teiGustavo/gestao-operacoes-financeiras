<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OperationReportRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OperationReportCsvDownloadController extends Controller
{
    public function __invoke(OperationReportRun $operationReportRun): BinaryFileResponse|RedirectResponse
    {
        abort_unless(auth()->id() === $operationReportRun->requested_by_user_id, 403);

        if (
            $operationReportRun->status !== OperationReportRun::STATUS_COMPLETED
            || $operationReportRun->output_file_path === null
            || ! Storage::disk('local')->exists($operationReportRun->output_file_path)
        ) {
            return back()->withErrors(['report' => 'Relatorio ainda nao esta disponivel para download.']);
        }

        return response()->download(
            Storage::disk('local')->path($operationReportRun->output_file_path),
            'operations-report-'.$operationReportRun->id.'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
