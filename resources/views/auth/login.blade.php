@extends('layouts.public')

@section('page_title', 'Login')

@section('content')
    <main class="mx-auto flex min-h-screen w-full max-w-md items-center px-6 py-10">
        <div class="w-full rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-semibold">Entrar</h1>
            <p class="mt-1 text-sm text-slate-600">Acesse com email/nome de usuário e senha.</p>

            @if (session('status'))
                <div class="mt-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if ($demoCredentials['shouldDisplay'])
                <div class="mt-4 rounded-md border border-blue-200 bg-blue-50 px-3 py-3 text-sm text-blue-900">
                    <p class="font-medium">Usuário administrador (desenvolvimento)</p>
                    <p class="mt-1">Nome de Usuário: <span class="font-mono">{{ $demoCredentials['username'] }}</span></p>
                    <p>E-mail: <span class="font-mono">{{ $demoCredentials['email'] }}</span></p>
                    <p>Senha: <span class="font-mono">{{ $demoCredentials['password'] }}</span></p>
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form class="mt-5 space-y-4" method="POST" action="{{ route('login.store') }}">
                @csrf

                <div>
                    <label for="login" class="mb-1 block text-sm font-medium text-slate-700">Email ou Nome de Usuário</label>
                    <input
                        id="login"
                        name="login"
                        type="text"
                        value="{{ old('login', $demoCredentials['email']) }}"
                        required
                        autofocus
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    >

                    @error('login')
                        <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm font-medium text-slate-700">Senha</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        value="{{ $demoCredentials['password'] }}"
                        required
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    >

                    @error('password')
                        <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    Lembrar de mim
                </label>

                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700"
                >
                    Entrar
                </button>
            </form>
        </div>
    </main>
@endsection
