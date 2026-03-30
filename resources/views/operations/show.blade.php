@php
    use App\Domain\Operation\OperationStatus;
    use App\Domain\Operation\ProductType;
    use Illuminate\Support\Carbon;

    $statusOptions = OperationStatus::cases();
    $statusLabels = collect($statusOptions)
        ->mapWithKeys(fn ($status) => [$status->value => $status->label()])
        ->toArray();
    $getStatusLabel = fn ($statusValue) => $statusLabels[$statusValue] ?? $statusValue;

    $formatDateToBrazilian = fn ($date) => Carbon::make($date)?->format('d/m/Y') ?? '-';
    $formatToBRLCurrency = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
@endphp

@extends('layouts.public')

@section('page_title', 'Detalhe da Operação')

@section('content')
    <main class="mx-auto w-full max-w-5xl px-6 py-10">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-6 flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">Operação #{{ $operation['id'] }}</h1>
                    <p class="mt-1 text-sm text-slate-600">Detalhes da operação e histórico de alteracoes.</p>
                </div>

                <a
                    href="{{ route('operations.index') }}"
                    class="inline-flex items-center rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                >
                    Voltar para esteira
                </a>
            </div>

            @if (session('status'))
                <div class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="rounded-md border border-slate-200 p-4">
                    <h2 class="mb-3 text-lg font-semibold">Dados principais</h2>
                    <dl class="space-y-2 text-sm">
                        <div>
                            <dt class="font-bold text-slate-700">Cliente</dt>
                            <dd>{{ $operation['client']['name'] }}</dd>
                        </div>
                        <div>
                            <dt class="font-bold text-slate-700">CPF</dt>
                            <dd>{{ $operation['client']['cpf'] }}</dd>
                        </div>
                        <div>
                            <dt class="font-bold text-slate-700">Valor da operação</dt>
                            <dd>{{ $formatToBRLCurrency($operation['requested_value']) }}</dd>
                        </div>
                        <div>
                            <dt class="font-bold text-slate-700">Status atual</dt>
                            <dd>{{ $operation['status']['label'] }}</dd>
                        </div>
                        <div>
                            <dt class="font-bold text-slate-700">Produto</dt>
                            <dd>{{ ProductType::from($operation['product_type'])->label() }}</dd>
                        </div>
                        <div>
                            <dt class="font-bold text-slate-700">Conveniada</dt>
                            <dd>{{ $operation['agreement']['name'] }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-md border border-slate-200 p-4">
                    <h2 class="mb-3 text-lg font-semibold">Alterar status</h2>

                    <form
                        method="POST"
                        action="{{ route('operations.status.update', ['operation' => $operation['id']]) }}"
                        class="space-y-3"
                        x-data="{ selectedStatus: '{{ old('status', $operation['status']['value']) }}' }"
                    >
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="redirect_to"
                               value="{{ route('operations.show', ['operation' => $operation['id']]) }}">

                        <div>
                            <label for="status" class="mb-1 block text-sm font-medium text-slate-700">Novo
                                status</label>
                            <select id="status" name="status"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                    x-model="selectedStatus">
                                @foreach ($statusOptions as $statusOption)
                                    @php
                                        $isSelectable = $statusSelectability[$statusOption->value] ?? false;
                                        $isCurrentStatus = $operation['status']['value'] === $statusOption->value;
                                    @endphp

                                    <option
                                        value="{{ $statusOption->value }}"
                                        @selected(old('status', $operation['status']['value']) === $statusOption->value)
                                        @disabled(! $isSelectable)
                                        @if (! $isSelectable && isset($statusBlockedReasons[$statusOption->value])) title="{{ $statusBlockedReasons[$statusOption->value] }}" @endif
                                    >
                                        {{ $statusOption->label() }}{{ $isCurrentStatus ? ' (atual)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-500">Status indisponiveis ficam bloqueados para
                                selecao.</p>

                            @php
                                $blockedStatuses = collect($statusOptions)
                                    ->filter(fn ($statusOption) => ! ($statusSelectability[$statusOption->value] ?? false))
                                    ->map(fn ($statusOption) => [
                                        'label' => $statusOption->label(),
                                        'reason' => $statusBlockedReasons[$statusOption->value] ?? 'Sem permissao para transicao.',
                                    ])
                                    ->values();
                            @endphp

                            @if ($blockedStatuses->isNotEmpty())
                                <ul class="mt-2 list-disc pl-4 text-xs text-slate-500">
                                    @foreach ($blockedStatuses as $blockedStatus)
                                        <li><span
                                                class="font-medium">{{ $blockedStatus['label'] }}:</span> {{ $blockedStatus['reason'] }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        <div x-show="selectedStatus === 'disbursed'" x-cloak>
                            <label for="payment_date" class="mb-1 block text-sm font-medium text-slate-700">Data de
                                pagamento (quando aplicavel)</label>
                            <input
                                id="payment_date"
                                name="payment_date"
                                type="date"
                                value="{{ old('payment_date', $operation['payment_date']) }}"
                                x-bind:disabled="selectedStatus !== 'disbursed'"
                                x-bind:required="selectedStatus === 'disbursed'"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                            >
                        </div>

                        <div>
                            <label for="notes" class="mb-1 block text-sm font-medium text-slate-700">Observacoes</label>
                            <textarea
                                id="notes"
                                name="notes"
                                rows="3"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                            >{{ old('notes') }}</textarea>
                        </div>

                        <button type="submit"
                                class="inline-flex rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700">
                            Atualizar status
                        </button>
                    </form>
                </div>
            </div>

            <div class="mt-6 rounded-md border border-slate-200 p-4">
                <h2 class="mb-3 text-lg font-semibold">Parcelas</h2>

                @if (empty($operation['installments']))
                    <p class="text-sm text-slate-600">Nao há parcelas registradas para esta operação.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-700">Parcela</th>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-700">Vencimento</th>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-700">Valor</th>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-700">Situação</th>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-700">Pagamento</th>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-700">Usuário</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($operation['installments'] as $installment)
                                    @php
                                        $isOverdueInstallment = ! $installment['paid']
                                            && Carbon::make($installment['due_date'])?->isPast();
                                    @endphp

                                    <tr @class(['bg-red-50' => $isOverdueInstallment])>
                                        <td class="px-3 py-2">{{ $installment['installment_number'] }}</td>
                                        <td class="px-3 py-2">{{ $formatDateToBrazilian($installment['due_date']) }}</td>
                                        <td class="px-3 py-2">{{ $formatToBRLCurrency($installment['value']) }}</td>
                                        <td class="px-3 py-2 {{ $isOverdueInstallment ? 'font-semibold text-red-700' : '' }}">
                                            {{ $installment['paid'] ? 'Pago' : ($isOverdueInstallment ? 'Vencida' : 'Em aberto') }}
                                        </td>
                                        <td class="px-3 py-2">{{ $formatDateToBrazilian($installment['paid_at']) }}</td>
                                        <td class="px-3 py-2">{{ $installment['paid_by_user']['name'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="mt-6 rounded-md border border-slate-200 p-4">
                <h2 class="mb-3 text-lg font-semibold">Histórico de status</h2>

                @if (empty($operation['history']))
                    <p class="text-sm text-slate-600">Ainda nao ha alteracoes registradas.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-slate-700">Quando</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-700">De</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-700">Para</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-700">Usuario</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-700">Observacoes</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                            @foreach ($operation['history'] as $historyItem)
                                <tr>
                                    <td class="px-3 py-2">{{ $formatDateToBrazilian($historyItem['changed_at']) }}</td>
                                    <td class="px-3 py-2">{{ $getStatusLabel($historyItem['previous_status']) ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $getStatusLabel($historyItem['new_status']) }}</td>
                                    <td class="px-3 py-2">{{ $historyItem['changed_by_user']['name'] ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $historyItem['notes'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
    </main>
@endsection

