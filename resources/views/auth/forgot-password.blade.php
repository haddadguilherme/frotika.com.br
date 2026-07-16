@extends('layouts.guest')

@section('title', 'Recuperar senha | Frotika')

@section('content')
    <div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-2 lg:items-center">
        <section>
            <p
                class="inline-flex items-center rounded-full bg-accent-500/20 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-950">
                Recuperacao de acesso
            </p>
            <h1 class="mt-4 font-display text-3xl font-semibold text-white sm:text-4xl">Esqueceu sua senha?</h1>
            <p class="mt-3 max-w-lg text-sm text-brand-100 sm:text-base">
                Informe seu e-mail e enviaremos um link para criar uma nova senha com seguranca.
            </p>
        </section>

        <x-ui.card class="mx-auto w-full max-w-md">
            <h2 class="font-display text-2xl font-semibold text-slate-900">Solicitar redefinicao</h2>
            <p class="mt-2 text-sm text-slate-600">Use o e-mail cadastrado no Frotika.</p>

            <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
                @csrf

                <x-ui.input label="E-mail" name="email" type="email" placeholder="voce@empresa.com.br"
                    autocomplete="email" required />

                <x-ui.button type="submit" class="w-full justify-center">
                    Enviar link de redefinicao
                </x-ui.button>
            </form>

            <p class="mt-4 text-center text-sm text-slate-600">
                Lembrou da senha?
                <a href="{{ route('login') }}" class="font-medium text-brand-700 hover:text-brand-800">Voltar para entrar</a>
            </p>
        </x-ui.card>
    </div>
@endsection
