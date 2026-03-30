@extends('layouts.public')

@section('page_title', $page['title'])

@section('content')
    <main class="mx-auto flex min-h-screen w-full max-w-md items-center px-6 py-10">
        <div class="w-full rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-semibold">{{ $page['heading'] }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $page['subtitle'] }}</p>

            <x-feedback.alert :message="session('status')" variant="success" />

            @if ($demoCredentials['shouldDisplay'])
                <x-feedback.alert variant="info" class="py-3">
                    <p class="font-medium">Usuário administrador (desenvolvimento)</p>
                    <p class="mt-1">Nome de Usuário: <span class="font-mono">{{ $demoCredentials['username'] }}</span></p>
                    <p>E-mail: <span class="font-mono">{{ $demoCredentials['email'] }}</span></p>
                    <p>Senha: <span class="font-mono">{{ $demoCredentials['password'] }}</span></p>
                </x-feedback.alert>
            @endif

            <x-feedback.alert :message="$errors->first()" variant="error" />

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
