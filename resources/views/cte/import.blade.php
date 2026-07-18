@extends('layouts.app')

@section('title', 'Importar CT-e | Frotika')

@section('content')
    <x-ui.page-header title="Importar CT-e" subtitle="Envie o XML do CT-e para cadastrar parceiros, vincular o veículo e lançar a receita no fluxo de caixa." />

    <div class="mx-auto max-w-2xl">
        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <form method="POST" action="{{ route('cte.import.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf

                <div>
                    <label for="xml" class="mb-1 block text-sm font-medium text-slate-700">Arquivo XML do CT-e</label>
                    <input type="file" name="xml" id="xml" accept=".xml,text/xml,application/xml" required
                        class="block w-full rounded-md border border-slate-300 bg-white text-sm text-slate-700 file:mr-3 file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20" />
                    @error('xml')
                        <p class="mt-1 text-xs text-danger-700">{{ $message }}</p>
                    @enderror
                    <p class="mt-2 text-xs text-slate-500">
                        Aceita o XML autorizado do CT-e (modelo 57, versão 4.00). O arquivo é guardado em área privada do
                        grupo para reprocessamento futuro.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                    <x-ui.link-button href="{{ route('cte.index') }}" variant="secondary">Cancelar</x-ui.link-button>
                    <x-ui.button type="submit" variant="primary">Importar</x-ui.button>
                </div>
            </form>
        </div>
    </div>
@endsection
