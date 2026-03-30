<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\OperationStatusTransitions;
use App\Infrastructure\Queries\Operation\OperationDetailsQuery;
use App\Models\Operation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class OperationDetailsController extends Controller
{
    public function __construct(private readonly OperationDetailsQuery $operationDetailsQuery) {}

    public function __invoke(Operation $operation): JsonResponse|View
    {
        $details = $this->operationDetailsQuery->findById($operation->id);

        if ($details === null) {
            abort(404, 'Operacao nao encontrada.');
        }

        if (! request()->expectsJson()) {
            $currentStatus = OperationStatus::from($details['status']['value']);

            return view('operations.show', [
                'operation' => $details,
                'statusSelectability' => collect(OperationStatus::cases())
                    ->mapWithKeys(static fn (OperationStatus $targetStatus): array => [
                        $targetStatus->value => OperationStatusTransitions::canTransition($currentStatus, $targetStatus),
                    ])
                    ->all(),
                'statusBlockedReasons' => OperationStatusTransitions::blockedReasonsFrom($currentStatus),
            ]);
        }

        return response()->json([
            'data' => $details,
        ]);
    }
}
