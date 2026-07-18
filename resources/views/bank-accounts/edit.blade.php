@extends('layouts.app')

@section('title', 'Editar conta bancária | Frotika')

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-ui.page-header title="Editar conta bancária" :subtitle="$account->getAttribute('name')">
            <x-slot:actions>
                <x-ui.link-button href="{{ route('bank-accounts.index') }}" variant="secondary">Voltar</x-ui.link-button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.card class="border-slate-200 bg-white">
            <form method="POST" action="{{ route('bank-accounts.update', ['account' => $account->getKey()]) }}">
                @csrf
                @method('PUT')
                @include('bank-accounts._form', ['account' => $account])

                <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-4">
                    <x-ui.link-button href="{{ route('bank-accounts.index') }}" variant="secondary">Cancelar</x-ui.link-button>
                    <x-ui.button type="submit">Salvar alterações</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
