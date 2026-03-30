<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListOperationsRequest;
use App\Http\Resources\Operation\OperationListResource;
use App\Http\ViewModels\Operation\OperationListViewModel;
use App\Infrastructure\Queries\Operation\OperationListQuery;
use App\Infrastructure\Queries\Operation\OperationReportRunLatestQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class OperationListController extends Controller
{
    public function __construct(
        private readonly OperationListQuery $operationListQuery,
        private readonly OperationReportRunLatestQuery $operationReportRunLatestQuery,
        private readonly OperationListViewModel $operationListViewModel,
    ) {}

    public function __invoke(ListOperationsRequest $request): JsonResponse|View
    {
        $validatedFilters = $request->validated();
        $result = $this->operationListQuery->list($validatedFilters);

        if (! $request->expectsJson()) {
            $reportRuns = $this->operationReportRunLatestQuery->latestForUser(
                userId: (int) $request->user()->id,
            );

            return view('operations.index', $this->operationListViewModel->toArray(
                $result,
                $validatedFilters,
                $reportRuns,
            ));
        }

        return OperationListResource::collection($result)->response();
    }
}
