@extends('layouts.public')

@section('page_title', 'Esteira de Operacoes')

@section('content')
    <main class="mx-auto w-full max-w-6xl px-6 py-10">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-6 flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">Esteira de Operacoes</h1>
                    <p class="mt-1 text-sm text-slate-600">Filtre e acompanhe as operacoes cadastradas.</p>
                </div>

                <a
                    href="{{ route('operations.index') }}"
                    class="inline-flex items-center rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                >
                    Limpar filtros
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

            <form method="GET" action="{{ route('operations.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-5">
                <div>
                    <label for="status" class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                    <select id="status" name="status" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Todos</option>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="operation" class="mb-1 block text-sm font-medium text-slate-700">Operacao</label>
                    <input
                        id="operation"
                        name="operation"
                        type="number"
                        min="1"
                        value="{{ $filters['operation'] ?? '' }}"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                    >
                </div>

                <div>
                    <label for="product" class="mb-1 block text-sm font-medium text-slate-700">Produto</label>
                    <select id="product" name="product" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Todos</option>
                        @foreach ($productOptions as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['product'] ?? null) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="agreement" class="mb-1 block text-sm font-medium text-slate-700">Conveniada</label>
                    <select id="agreement" name="agreement" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Todas</option>
                        @foreach ($agreementOptions as $id => $name)
                            <option value="{{ $id }}" @selected((string) ($filters['agreement'] ?? '') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="per_page" class="mb-1 block text-sm font-medium text-slate-700">Por pagina</label>
                    <select id="per_page" name="per_page" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @foreach ([15, 30, 50, 100] as $perPageOption)
                            <option value="{{ $perPageOption }}" @selected((int) ($filters['per_page'] ?? 15) === $perPageOption)>
                                {{ $perPageOption }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-3 lg:col-span-5">
                    <button type="submit" class="inline-flex rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700">
                        Aplicar filtros
                    </button>
                </div>
            </form>

            @if ($operations->isEmpty())
                <div class="mt-6 rounded-md bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    Nenhuma operacao encontrada para os filtros informados.
                </div>
            @else
                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-700">Codigo</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-700">Cliente</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-700">CPF</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-700">Valor</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-700">Status</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-700">Produto</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-700">Conveniada</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-700">Detalhe</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-700">Acao rapida</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($operations as $operation)
                                <tr>
                                    <td class="px-3 py-2">
                                        <a href="{{ route('operations.show', ['operation' => $operation['operation_code']]) }}" class="text-blue-700 underline">
                                            {{ $operation['operation_code'] }}
                                        </a>
                                    </td>
                                    <td class="px-3 py-2">{{ $operation['client_name'] }}</td>
                                    <td class="px-3 py-2">{{ $operation['cpf'] }}</td>
                                    <td class="px-3 py-2">{{ number_format((float) $operation['operation_value'], 2, ',', '.') }}</td>
                                    <td class="px-3 py-2">{{ $operation['status']['label'] }}</td>
                                    <td class="px-3 py-2">{{ $operation['product']['label'] }}</td>
                                    <td class="px-3 py-2">{{ $operation['agreement']['name'] }}</td>
                                    <td class="px-3 py-2">
                                        <a
                                            href="{{ route('operations.show', ['operation' => $operation['operation_code']]) }}"
                                            class="inline-flex items-center rounded-md border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 transition hover:bg-slate-100"
                                        >
                                            Detalhes
                                        </a>
                                    </td>
                                    <td class="px-3 py-2">
                                        <form
                                            method="POST"
                                            action="{{ route('operations.status.update', ['operation' => $operation['operation_code']]) }}"
                                            class="grid gap-2"
                                            x-data="{ selectedStatus: '{{ $operation['status']['value'] }}' }"
                                        >
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">

                                            <select name="status" class="w-full rounded-md border border-slate-300 px-2 py-1 text-xs" x-model="selectedStatus">
                                                @foreach ($statusOptions as $statusValue => $statusLabel)
                                                    @php
                                                        $isSelectable = $statusSelectabilityByCurrentStatus[$operation['status']['value']][$statusValue] ?? false;
                                                        $isCurrentStatus = $operation['status']['value'] === $statusValue;
                                                        $blockedReason = $statusBlockedReasonsByCurrentStatus[$operation['status']['value']][$statusValue] ?? 'Sem permissao para transicao.';
                                                    @endphp

                                                    <option
                                                        value="{{ $statusValue }}"
                                                        @selected($isCurrentStatus)
                                                        @disabled(! $isSelectable)
                                                        @if (! $isSelectable) title="{{ $blockedReason }}" @endif
                                                    >
                                                        {{ $statusLabel }}{{ $isCurrentStatus ? ' (atual)' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>

                                            <p class="text-[11px] text-slate-500">Opcoes bloqueadas mostram motivo ao passar o mouse.</p>

                                            <div x-show="selectedStatus === 'disbursed'" x-cloak>
                                                <input
                                                    type="date"
                                                    name="payment_date"
                                                    x-bind:disabled="selectedStatus !== 'disbursed'"
                                                    x-bind:required="selectedStatus === 'disbursed'"
                                                    class="w-full rounded-md border border-slate-300 px-2 py-1 text-xs"
                                                >
                                            </div>

                                            <button type="submit" class="inline-flex items-center justify-center rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white transition hover:bg-slate-700">
                                                Atualizar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $operations->onEachSide(1)->links() }}
                </div>
            @endif
        </section>
    </main>
@endsection

