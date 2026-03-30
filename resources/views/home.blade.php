@extends('layouts.public')

@section('page_title', config('app.name', 'Gestao de Operacoes Financeiras'))

@section('content')
    <main class="mx-auto flex min-h-screen w-full max-w-2xl items-center px-6 py-10">
        <section class="w-full rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
            <h1 class="text-3xl font-semibold">Bem-vindo(a) ao sistema</h1>
            <p class="mt-3 text-sm text-slate-600">
                Gerencie operacoes financeiras com autenticação e rastreabilidade de status.
            </p>

            <div class="mt-6 flex flex-wrap gap-3">
                @guest
                    @if (Route::has('login'))
                        <a
                            href="{{ route('login') }}"
                            class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700"
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
                                class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                            >
                                Sair
                            </button>
                        </form>
                    @endif
                @endauth
            </div>
        </section>
    </main>
@endsection
