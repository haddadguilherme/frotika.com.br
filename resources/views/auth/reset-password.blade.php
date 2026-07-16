@extends('layouts.guest')

@section('title', 'Redefinir senha | Frotika')

@section('content')
    <div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-2 lg:items-center">
        <section>
            <p
                class="inline-flex items-center rounded-full bg-accent-500/20 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-950">
                Acesso seguro
            </p>
            <h1 class="mt-4 font-display text-3xl font-semibold text-white sm:text-4xl">Criar nova senha</h1>
            <p class="mt-3 max-w-lg text-sm text-brand-100 sm:text-base">
                Defina uma nova senha para voltar ao painel da sua transportadora.
            </p>
        </section>

        <x-ui.card class="mx-auto w-full max-w-md">
            <h2 class="font-display text-2xl font-semibold text-slate-900">Redefinir senha</h2>
            <p class="mt-2 text-sm text-slate-600">Escolha uma senha forte para sua conta.</p>

            <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}" />

                <x-ui.input label="E-mail" name="email" type="email" :value="$email" placeholder="voce@empresa.com.br"
                    autocomplete="email" required />

                <x-ui.input label="Nova senha" name="password" type="password" placeholder="No minimo 8 caracteres"
                    autocomplete="new-password" required />

                <x-ui.input label="Confirmar nova senha" name="password_confirmation" type="password"
                    placeholder="Repita a nova senha" autocomplete="new-password" required />

                <x-ui.button type="submit" class="w-full justify-center">
                    Redefinir senha
                </x-ui.button>
            </form>
        </x-ui.card>
    </div>
@endsection
