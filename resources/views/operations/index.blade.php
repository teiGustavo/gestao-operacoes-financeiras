@extends('layouts.public')

@section('page_title', 'Esteira de Operacoes')

@section('content')
    <main class="mx-auto w-full px-6 py-10">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-6 flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">Esteira de Operacoes</h1>
                    <p class="mt-1 text-sm text-slate-600">Filtre e acompanhe as operacoes cadastradas.</p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        id="refresh-runs-button"
                        data-refresh-url="{{ route('operations.runs.status') }}"
                        class="inline-flex items-center rounded-md border border-sky-300 px-3 py-2 text-sm font-medium text-sky-700 transition hover:bg-sky-50"
                    >
                        Atualizar status
                    </button>

                    <form action="{{ route('operations.import.csv') }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-2">
                        @csrf
                        <input
                            type="file"
                            name="csv_file"
                            accept=".csv,text/csv"
                            class="block w-full max-w-56 rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-700"
                            required
                        >
                        <button
                            type="submit"
                            class="inline-flex items-center rounded-md border border-indigo-300 px-3 py-2 text-sm font-medium text-indigo-700 transition hover:bg-indigo-50"
                        >
                            Importar CSV
                        </button>
                    </form>

                    <a
                        href="{{ route('operations.report.csv', request()->only(['status', 'operation', 'product', 'agreement'])) }}"
                        class="inline-flex items-center rounded-md border border-emerald-300 px-3 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-50"
                    >
                        Exportar CSV
                    </a>

                    <a
                        href="{{ route('operations.index') }}"
                        class="inline-flex items-center rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                    >
                        Limpar filtros
                    </a>
                </div>
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

            <section class="mt-6 rounded-lg border border-slate-200 p-4">
                <h2 class="text-sm font-semibold text-slate-800">Ultimas importacoes</h2>
                <p class="mt-1 text-xs text-slate-500">Acompanhe o status das importacoes CSV solicitadas por voce.</p>

                @if ($recentImportRuns === [])
                    <p class="mt-3 text-sm text-slate-600">Nenhuma importacao solicitada ainda.</p>
                @else
                    <div class="mt-3 overflow-x-auto" id="recent-import-runs-table-wrapper">
                        <table class="min-w-full divide-y divide-slate-200 text-xs">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-slate-700">Run</th>
                                    <th class="px-3 py-2 text-left font-medium text-slate-700">Status</th>
                                    <th class="px-3 py-2 text-left font-medium text-slate-700">Total</th>
                                    <th class="px-3 py-2 text-left font-medium text-slate-700">Importadas</th>
                                    <th class="px-3 py-2 text-left font-medium text-slate-700">Rejeitadas</th>
                                    <th class="px-3 py-2 text-left font-medium text-slate-700">Finalizado em</th>
                                    <th class="px-3 py-2 text-left font-medium text-slate-700">Motivo da falha</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100" id="recent-import-runs-body">
                                @foreach ($recentImportRuns as $importRun)
                                    <tr>
                                        <td class="px-3 py-2">#{{ $importRun['id'] }}</td>
                                        <td class="px-3 py-2">{{ $importRun['status_label'] }}</td>
                                        <td class="px-3 py-2">{{ $importRun['total_rows'] }}</td>
                                        <td class="px-3 py-2">{{ $importRun['imported_rows'] }}</td>
                                        <td class="px-3 py-2">{{ $importRun['rejected_rows'] }}</td>
                                        <td class="px-3 py-2">{{ $importRun['finished_at'] ?? '-' }}</td>
                                        <td class="px-3 py-2">{{ $importRun['failure_message'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="mt-6 rounded-lg border border-slate-200 p-4">
                <h2 class="text-sm font-semibold text-slate-800">Ultimos relatorios</h2>
                <p class="mt-1 text-xs text-slate-500">Acompanhe o status e baixe os CSVs finalizados.</p>

                @if ($recentReportRuns === [])
                    <p class="mt-3 text-sm text-slate-600">Nenhum relatorio solicitado ainda.</p>
                @else
                    <div class="mt-3 overflow-x-auto" id="recent-report-runs-table-wrapper">
                        <table class="min-w-full divide-y divide-slate-200 text-xs">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-slate-700">Run</th>
                                    <th class="px-3 py-2 text-left font-medium text-slate-700">Status</th>
                                    <th class="px-3 py-2 text-left font-medium text-slate-700">Linhas</th>
                                    <th class="px-3 py-2 text-left font-medium text-slate-700">Finalizado em</th>
                                    <th class="px-3 py-2 text-left font-medium text-slate-700">Motivo da falha</th>
                                    <th class="px-3 py-2 text-left font-medium text-slate-700">Acao</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100" id="recent-report-runs-body">
                                @foreach ($recentReportRuns as $reportRun)
                                    <tr>
                                        <td class="px-3 py-2">#{{ $reportRun['id'] }}</td>
                                        <td class="px-3 py-2">{{ $reportRun['status_label'] }}</td>
                                        <td class="px-3 py-2">{{ $reportRun['total_rows'] }}</td>
                                        <td class="px-3 py-2">{{ $reportRun['finished_at'] ?? '-' }}</td>
                                        <td class="px-3 py-2">
                                            @if ($reportRun['status'] === 'failed')
                                                {{ $reportRun['failure_message'] ?? 'Falha sem detalhe' }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">
                                            @if ($reportRun['download_url'])
                                                <a
                                                    href="{{ $reportRun['download_url'] }}"
                                                    class="inline-flex items-center rounded-md border border-emerald-300 px-2 py-1 font-medium text-emerald-700 hover:bg-emerald-50"
                                                >
                                                    Baixar CSV
                                                </a>
                                            @else
                                                <span class="text-slate-500">Aguardando</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

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
                                    <td class="px-3 py-2">{{ $operation['operation_value_display'] }}</td>
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
                                                @foreach ($operation['quick_status_options'] as $statusOption)
                                                    <option
                                                        value="{{ $statusOption['value'] }}"
                                                        @selected($statusOption['is_current'])
                                                        @disabled(! $statusOption['is_selectable'])
                                                        @if (! $statusOption['is_selectable']) title="{{ $statusOption['blocked_reason'] }}" @endif
                                                    >
                                                        {{ $statusOption['label'] }}{{ $statusOption['is_current'] ? ' (atual)' : '' }}
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

    <script>
        (() => {
            const refreshButton = document.getElementById('refresh-runs-button');

            if (!refreshButton) {
                return;
            }

            const refreshUrl = refreshButton.dataset.refreshUrl;
            const importRunsBody = document.getElementById('recent-import-runs-body');
            const reportRunsBody = document.getElementById('recent-report-runs-body');

            if (!refreshUrl || !importRunsBody || !reportRunsBody) {
                return;
            }

            const escapeHtml = (value) => {
                return String(value)
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            };

            const reportRowHtml = (reportRun) => {
                const failureReason = reportRun.status === 'failed'
                    ? escapeHtml(reportRun.failure_message ?? 'Falha sem detalhe')
                    : '-';

                const actionHtml = reportRun.download_url
                    ? `<a href="${escapeHtml(reportRun.download_url)}" class="inline-flex items-center rounded-md border border-emerald-300 px-2 py-1 font-medium text-emerald-700 hover:bg-emerald-50">Baixar CSV</a>`
                    : '<span class="text-slate-500">Aguardando</span>';

                return `
                    <tr>
                        <td class="px-3 py-2">#${escapeHtml(reportRun.id)}</td>
                        <td class="px-3 py-2">${escapeHtml(reportRun.status_label)}</td>
                        <td class="px-3 py-2">${escapeHtml(reportRun.total_rows)}</td>
                        <td class="px-3 py-2">${escapeHtml(reportRun.finished_at ?? '-')}</td>
                        <td class="px-3 py-2">${failureReason}</td>
                        <td class="px-3 py-2">${actionHtml}</td>
                    </tr>
                `;
            };

            const importRowHtml = (importRun) => {
                return `
                    <tr>
                        <td class="px-3 py-2">#${escapeHtml(importRun.id)}</td>
                        <td class="px-3 py-2">${escapeHtml(importRun.status_label)}</td>
                        <td class="px-3 py-2">${escapeHtml(importRun.total_rows)}</td>
                        <td class="px-3 py-2">${escapeHtml(importRun.imported_rows)}</td>
                        <td class="px-3 py-2">${escapeHtml(importRun.rejected_rows)}</td>
                        <td class="px-3 py-2">${escapeHtml(importRun.finished_at ?? '-')}</td>
                        <td class="px-3 py-2">${escapeHtml(importRun.failure_message ?? '-')}</td>
                    </tr>
                `;
            };

            refreshButton.addEventListener('click', async () => {
                refreshButton.disabled = true;

                try {
                    const response = await fetch(refreshUrl, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        console.error('Falha ao atualizar paineis');

                        return;
                    }

                    const payload = await response.json();
                    const recentImportRuns = payload?.data?.recent_import_runs ?? [];
                    const recentReportRuns = payload?.data?.recent_report_runs ?? [];

                    importRunsBody.innerHTML = recentImportRuns.map(importRowHtml).join('');
                    reportRunsBody.innerHTML = recentReportRuns.map(reportRowHtml).join('');
                } catch (error) {
                    console.error(error);
                } finally {
                    refreshButton.disabled = false;
                }
            });
        })();
    </script>
@endsection

