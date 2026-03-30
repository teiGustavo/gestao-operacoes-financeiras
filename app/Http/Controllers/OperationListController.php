<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListOperationsRequest;
use App\Http\Resources\Operation\OperationListResource;
use App\Http\ViewModels\Operation\OperationListViewModel;
use App\Infrastructure\Queries\Operation\OperationListQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class OperationListController extends Controller
{
    public function __construct(
        private readonly OperationListQuery $operationListQuery,
        private readonly OperationListViewModel $operationListViewModel,
    ) {}

    public function __invoke(ListOperationsRequest $request): JsonResponse|View
    {
        $validatedFilters = $request->validated();
        $result = $this->operationListQuery->list($validatedFilters);

        if (! $request->expectsJson()) {
            return view('operations.index', $this->operationListViewModel->toArray(
                operations: $result,
                filters: $validatedFilters,
            ));
        }

        return OperationListResource::collection($result)->response();
    }
}
