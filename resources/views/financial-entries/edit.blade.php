@extends('layouts.app')

@section('title', 'Editar lançamento | Frotika')

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-ui.page-header title="Editar lançamento" :subtitle="$entry->getAttribute('description')">
            <x-slot:actions>
                <x-ui.link-button href="{{ route('financial-entries.show', ['entry' => $entry->getKey()]) }}" variant="secondary">Voltar</x-ui.link-button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.card class="border-slate-200 bg-white">
            <form method="POST" action="{{ route('financial-entries.update', ['entry' => $entry->getKey()]) }}">
                @csrf
                @method('PUT')
                @include('financial-entries._form', ['entry' => $entry])

                <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-4">
                    <x-ui.link-button href="{{ route('financial-entries.show', ['entry' => $entry->getKey()]) }}" variant="secondary">Cancelar</x-ui.link-button>
                    <x-ui.button type="submit">Salvar alterações</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
