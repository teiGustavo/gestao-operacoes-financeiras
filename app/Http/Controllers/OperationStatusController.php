<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Operation\Data\ChangeOperationStatusInput;
use App\Application\Operation\UseCases\ChangeOperationStatusUseCase;
use App\Domain\Operation\OperationStatus;
use App\Domain\Shared\Result\DomainError;
use App\Domain\Shared\Result\ErrorCode;
use App\Http\Requests\UpdateOperationStatusRequest;
use App\Http\Resources\Operation\OperationStatusUpdateErrorResource;
use App\Http\Resources\Operation\OperationStatusUpdateSuccessResource;
use App\Models\Operation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class OperationStatusController extends Controller
{
    public function __construct(private readonly ChangeOperationStatusUseCase $changeOperationStatusUseCase) {}

    public function __invoke(UpdateOperationStatusRequest $request, Operation $operation): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        $result = $this->changeOperationStatusUseCase->execute(new ChangeOperationStatusInput(
            operationId: $operation->id,
            newStatus: OperationStatus::from($validated['status']),
            changedByUserId: (int) $request->user()->id,
            notes: $validated['notes'] ?? null,
            paymentDate: $validated['payment_date'] ?? null,
        ));

        if ($result->isFailure()) {
            $statusCode = $result->firstError()?->code === ErrorCode::OperationNotFound ? 404 : 422;

            if (! $request->expectsJson()) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'status' => $result->firstError()?->message ?? 'Falha ao alterar status da operacao.',
                    ]);
            }

            return response()->json(OperationStatusUpdateErrorResource::make([
                'message' => 'Falha ao alterar status da operacao.',
                'errors' => array_map(
                    static fn (DomainError $error): array => [
                        'code' => $error->code->value,
                        'message' => $error->message,
                        'context' => $error->context,
                    ],
                    $result->errors(),
                ),
            ])->resolve($request), $statusCode);
        }

        if (! $request->expectsJson()) {
            $redirectTo = $validated['redirect_to'] ?? route('operations.show', ['operation' => $operation->id]);

            return redirect()
                ->to($redirectTo)
                ->with('status', 'Status da operacao atualizado com sucesso.');
        }

        return response()->json(OperationStatusUpdateSuccessResource::make([
            'message' => 'Status da operacao atualizado com sucesso.',
            'operation_id' => $result->value()->operationId,
            'status' => $result->value()->status->value,
            'payment_date' => $result->value()->paymentDate,
        ])->resolve($request));
    }
}
