@extends('layouts.guest')

@section('title', 'Criar conta | Frotika')

@section('content')
    <div class="mx-auto grid max-w-6xl gap-6 lg:grid-cols-[1fr_1.4fr]">
        <section>
            <p
                class="inline-flex items-center rounded-full bg-accent-500/20 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-950">
                Onboarding inicial
            </p>
            <h1 class="mt-4 font-display text-3xl font-semibold text-white sm:text-4xl">Comece seu DRE Veicular</h1>
            <p class="mt-3 text-sm text-brand-100 sm:text-base">
                Cadastre seu usuario e sua empresa para ativar o ambiente inicial do Frotika.
            </p>

            <x-ui.card class="mt-6 border-brand-700/20 bg-brand-100/40 p-4">
                <p class="text-sm text-slate-700">
                    O cadastro ja cria conta padrao Caixa e plano de contas financeiro para voce iniciar os lancamentos.
                </p>
            </x-ui.card>
        </section>

        <x-ui.card>
            <h2 class="font-display text-2xl font-semibold text-slate-900">Criar conta da transportadora</h2>
            <p class="mt-2 text-sm text-slate-600">Preencha os dados abaixo para liberar o painel.</p>

            <form method="POST" action="{{ route('register.store') }}" class="mt-6 grid gap-4 sm:grid-cols-2">
                @csrf

                <div class="sm:col-span-2">
                    <x-ui.input label="Nome" name="name" placeholder="Nome do responsavel" autocomplete="name"
                        required />
                </div>

                <x-ui.input label="E-mail" name="email" type="email" placeholder="voce@empresa.com.br"
                    autocomplete="email" required />

                <x-ui.input label="Senha" name="password" type="password" placeholder="No minimo 8 caracteres"
                    autocomplete="new-password" required />

                <div class="sm:col-span-2">
                    <x-ui.input label="Nome do grupo" name="group_name" placeholder="Grupo da transportadora" required />
                </div>

                <x-ui.input label="Razao social" name="company_legal_name" placeholder="Empresa de Transportes LTDA"
                    required />

                <x-ui.input label="Nome fantasia" name="company_trade_name" placeholder="Transportes Exemplo" required />

                <x-ui.input label="CNPJ" name="company_cnpj" placeholder="00000000000000" autocomplete="off" required />

                <div>
                    <label for="tax_regime" class="text-sm font-medium text-slate-700">Regime tributario</label>
                    <select id="tax_regime" name="tax_regime"
                        class="mt-2 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                        <option value="simples" @selected(old('tax_regime', 'simples') === 'simples')>Simples Nacional</option>
                        <option value="presumido" @selected(old('tax_regime') === 'presumido')>Lucro Presumido</option>
                        <option value="real" @selected(old('tax_regime') === 'real')>Lucro Real</option>
                    </select>
                    @error('tax_regime')
                        <p class="mt-1 text-sm text-danger-700">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2 mt-2 flex flex-wrap items-center justify-end gap-3">
                    <x-ui.link-button href="{{ route('login') }}" variant="secondary">
                        Ja tenho conta
                    </x-ui.link-button>

                    <x-ui.button type="submit">
                        Criar conta
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
