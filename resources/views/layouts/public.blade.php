<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>
            @yield('page_title', '') - {{ config('app.name', 'Gestao de Operacoes Financeiras') }}
        </title>
        <script src="https://cdn.tailwindcss.com"></script>
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
        <style>
            [x-cloak] {
                display: none !important;
            }
        </style>
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-3">
                <a href="{{ route('operations.index') }}" class="text-sm font-semibold text-slate-900">
                    Gestao de Operacoes
                </a>

                <nav class="flex flex-wrap items-center gap-2 text-sm">
                    @if (Route::has('operations.index'))
                        <a
                            href="{{ route('home') }}"
                            class="inline-flex items-center rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition {{ request()->routeIs('home') ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 text-slate-700 hover:bg-slate-100 active:bg-slate-200' }}"
                        >
                            Home
                        </a>
                    @endif

                @auth
                    @if (Route::has('operations.index'))
                        <a
                            href="{{ route('operations.index') }}"
                            class="inline-flex items-center rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition {{ request()->routeIs('operations.index') ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 text-slate-700 hover:bg-slate-100 active:bg-slate-200' }}"
                        >
                            Esteira
                        </a>
                    @endif
                @endauth

                    @guest
                        @if (Route::has('login'))
                            <a
                                href="{{ route('login') }}"
                                class="inline-flex items-center rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition {{ request()->routeIs('login') ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 text-slate-700 hover:bg-slate-100 active:bg-slate-200' }}"
                            >
                                Entrar
                            </a>
                        @endif
                    @endguest

                    @auth
                        @if (Route::has('logout'))
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="inline-flex items-center rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition hover:bg-slate-100"
                                >
                                    Sair
                                </button>
                            </form>
                        @endif
                    @endauth
                </nav>
            </div>
        </header>

        @yield('content')
    </body>
</html>

