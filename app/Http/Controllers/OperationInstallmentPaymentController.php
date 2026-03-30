<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PayInstallmentRequest;
use App\Http\Resources\Operation\OperationInstallmentPaymentResource;
use App\Models\Installment;
use App\Models\Operation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class OperationInstallmentPaymentController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        PayInstallmentRequest $request,
        Operation $operation,
        Installment $installment,
    ): JsonResponse|RedirectResponse {
        if ($installment->operation_id !== $operation->id) {
            abort(404, 'Parcela nao encontrada para a operacao informada.');
        }

        $wasAlreadyPaid = $installment->paid;

        if (! $wasAlreadyPaid) {
            DB::transaction(function () use ($request, $installment, $operation): void {
                $installment->forceFill([
                    'paid' => true,
                    'paid_at' => now(),
                    'paid_by_user_id' => (int) $request->user()->id,
                ])->save();

                $operation->forceFill([
                    'paid_installments_count' => Installment::query()
                        ->where('operation_id', $operation->id)
                        ->where('paid', true)
                        ->count(),
                ])->save();
            });
        }

        $message = $wasAlreadyPaid
            ? 'Parcela ja estava paga.'
            : 'Parcela marcada como paga com sucesso.';

        if ($request->expectsJson()) {
            return OperationInstallmentPaymentResource::make([
                'message' => $message,
                'operation_id' => $operation->id,
                'installment_id' => $installment->id,
                'paid' => true,
            ])->response();
        }

        $redirectTo = $request->validated('redirect_to')
            ?? route('operations.show', ['operation' => $operation->id]);

        return redirect()->to($redirectTo)->with('status', $message);
    }
}
