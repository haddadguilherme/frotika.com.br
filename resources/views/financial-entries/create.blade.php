@extends('layouts.app')

@section('title', 'Novo lançamento | Frotika')

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-ui.page-header title="Novo lançamento" subtitle="Registre uma receita ou despesa manual.">
            <x-slot:actions>
                <x-ui.link-button href="{{ route('financial-entries.index') }}" variant="secondary">Voltar</x-ui.link-button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.card class="border-slate-200 bg-white">
            <form method="POST" action="{{ route('financial-entries.store') }}">
                @csrf
                @include('financial-entries._form', ['entry' => null])

                <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-4">
                    <x-ui.link-button href="{{ route('financial-entries.index') }}" variant="secondary">Cancelar</x-ui.link-button>
                    <x-ui.button type="submit">Registrar lançamento</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
