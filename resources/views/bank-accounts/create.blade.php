@extends('layouts.app')

@section('title', 'Nova conta bancária | Frotika')

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-ui.page-header title="Nova conta bancária" subtitle="Cadastre uma conta, caixa ou carteira digital.">
            <x-slot:actions>
                <x-ui.link-button href="{{ route('bank-accounts.index') }}" variant="secondary">Voltar</x-ui.link-button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.card class="border-slate-200 bg-white">
            <form method="POST" action="{{ route('bank-accounts.store') }}">
                @csrf
                @include('bank-accounts._form', ['account' => null])

                <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-4">
                    <x-ui.link-button href="{{ route('bank-accounts.index') }}" variant="secondary">Cancelar</x-ui.link-button>
                    <x-ui.button type="submit">Cadastrar conta</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
