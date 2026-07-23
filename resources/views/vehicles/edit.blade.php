@extends('layouts.app')

@section('title', 'Editar veículo | Frotika')

@section('content')
    <div class="mx-auto max-w-3xl">
        <x-ui.page-header title="Editar veículo" :subtitle="Format::plate($vehicle->getAttribute('plate'))">
            <x-slot:actions>
                <x-ui.link-button href="{{ route('vehicles.show', ['vehicle' => $vehicle->getKey()]) }}" variant="secondary">Voltar</x-ui.link-button>
            </x-slot:actions>
        </x-ui.page-header>

        @if ($vehicle->getAttribute('provisioned'))
            <div class="mb-4 rounded-md border border-warning-500/40 bg-warning-50 px-4 py-2.5 text-sm text-warning-700">
                Veículo provisionado automaticamente pela importação de CT-e. Confirme placa e tipo, complete os dados e finalize o cadastro.
            </div>
        @endif

        <x-ui.card class="border-slate-200 bg-white">
            <form method="POST" action="{{ route('vehicles.update', ['vehicle' => $vehicle->getKey()]) }}">
                @csrf
                @method('PUT')
                @include('vehicles._form', ['vehicle' => $vehicle])

                <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-4">
                    <x-ui.link-button href="{{ route('vehicles.show', ['vehicle' => $vehicle->getKey()]) }}" variant="secondary">Cancelar</x-ui.link-button>
                    <x-ui.button type="submit">{{ $vehicle->getAttribute('provisioned') ? 'Completar cadastro' : 'Salvar alterações' }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
