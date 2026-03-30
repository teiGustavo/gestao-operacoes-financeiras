<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\OperationStatusTransitions;
use App\Domain\Operation\ProductType;
use App\Http\Requests\ListOperationsRequest;
use App\Infrastructure\Queries\Operation\OperationListQuery;
use App\Models\Agreement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class OperationListController extends Controller
{
    public function __construct(private readonly OperationListQuery $operationListQuery) {}

    public function __invoke(ListOperationsRequest $request): JsonResponse|View
    {
        $validatedFilters = $request->validated();
        $result = $this->operationListQuery->list($validatedFilters);

        if (! $request->expectsJson()) {
            return view('operations.index', [
                'operations' => $result,
                'filters' => $validatedFilters,
                'statusOptions' => collect(OperationStatus::cases())
                    ->mapWithKeys(static fn (OperationStatus $status): array => [$status->value => $status->label()])
                    ->all(),
                'statusSelectabilityByCurrentStatus' => collect(OperationStatus::cases())
                    ->mapWithKeys(static fn (OperationStatus $currentStatus): array => [
                        $currentStatus->value => collect(OperationStatus::cases())
                            ->mapWithKeys(static fn (OperationStatus $targetStatus): array => [
                                $targetStatus->value => OperationStatusTransitions::canTransition($currentStatus, $targetStatus),
                            ])
                            ->all(),
                    ])
                    ->all(),
                'statusBlockedReasonsByCurrentStatus' => collect(OperationStatus::cases())
                    ->mapWithKeys(static fn (OperationStatus $currentStatus): array => [
                        $currentStatus->value => OperationStatusTransitions::blockedReasonsFrom($currentStatus),
                    ])
                    ->all(),
                'productOptions' => collect(ProductType::cases())
                    ->mapWithKeys(static fn (ProductType $productType): array => [$productType->value => $productType->label()])
                    ->all(),
                'agreementOptions' => Agreement::query()
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all(),
            ]);
        }

        return response()->json([
            'data' => $result->items(),
            'meta' => [
                'current_page' => $result->currentPage(),
                'last_page' => $result->lastPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ]);
    }
}
