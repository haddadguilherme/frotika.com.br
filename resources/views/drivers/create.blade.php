@extends('layouts.app')

@section('title', 'Novo motorista | Frotika')

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-ui.page-header title="Novo motorista" subtitle="Cadastro rápido com alerta de vencimento da CNH.">
            <x-slot:actions>
                <x-ui.link-button href="{{ route('drivers.index') }}" variant="secondary">Voltar</x-ui.link-button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.card class="border-slate-200 bg-white">
            <form method="POST" action="{{ route('drivers.store') }}">
                @csrf
                @include('drivers._form', ['driver' => null])

                <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-4">
                    <x-ui.link-button href="{{ route('drivers.index') }}" variant="secondary">Cancelar</x-ui.link-button>
                    <x-ui.button type="submit">Cadastrar motorista</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
