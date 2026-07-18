@extends('layouts.app')

@section('title', 'Novo veículo | Frotika')

@section('content')
    <div class="mx-auto max-w-3xl">
        <x-ui.page-header title="Novo veículo" subtitle="Cadastre um cavalo, carreta ou caminhão da frota.">
            <x-slot:actions>
                <x-ui.link-button href="{{ route('vehicles.index') }}" variant="secondary">Voltar</x-ui.link-button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.card class="border-slate-200 bg-white">
            <form method="POST" action="{{ route('vehicles.store') }}">
                @csrf
                @include('vehicles._form', ['vehicle' => null])

                <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-4">
                    <x-ui.link-button href="{{ route('vehicles.index') }}" variant="secondary">Cancelar</x-ui.link-button>
                    <x-ui.button type="submit">Cadastrar veículo</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
