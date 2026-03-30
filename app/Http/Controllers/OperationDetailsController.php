<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\ViewModels\Operation\OperationDetailsViewModel;
use App\Infrastructure\Queries\Operation\OperationDetailsQuery;
use App\Models\Operation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class OperationDetailsController extends Controller
{
    public function __construct(
        private readonly OperationDetailsQuery $operationDetailsQuery,
        private readonly OperationDetailsViewModel $operationDetailsViewModel,
    ) {}

    public function __invoke(Operation $operation): JsonResponse|View
    {
        $details = $this->operationDetailsQuery->findById($operation->id);

        if ($details === null) {
            abort(404, 'Operacao nao encontrada.');
        }

        if (! request()->expectsJson()) {
            return view('operations.show', $this->operationDetailsViewModel->toArray($details));
        }

        return response()->json([
            'data' => $details,
        ]);
    }
}
