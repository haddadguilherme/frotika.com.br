@extends('layouts.app')

@section('title', 'Importar CT-e | Frotika')

@section('content')
    <x-ui.page-header title="Importar CT-e"
        subtitle="Selecione até 20 XMLs de uma vez. O Frotika processa em segundo plano, um a um, e avisa você quando terminar." />

    <div class="mx-auto max-w-2xl">
        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <form method="POST" action="{{ route('cte.import.store') }}" enctype="multipart/form-data" class="space-y-4"
                data-cte-import>
                @csrf

                <div>
                    <label for="xmls" class="mb-1 block text-sm font-medium text-slate-700">Arquivos XML do CT-e</label>
                    <input type="file" name="xmls[]" id="xmls" accept=".xml,text/xml,application/xml" multiple required
                        data-cte-import-input
                        class="block w-full rounded-md border border-slate-300 bg-white text-sm text-slate-700 file:mr-3 file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20" />
                    @error('xmls')
                        <p class="mt-1 text-xs text-danger-700">{{ $message }}</p>
                    @enderror
                    @error('xmls.*')
                        <p class="mt-1 text-xs text-danger-700">{{ $message }}</p>
                    @enderror
                    <p class="mt-2 text-xs text-slate-500">
                        Até 20 arquivos, 4 MB cada. XML autorizado do CT-e (modelo 57, versão 4.00). Cada arquivo é guardado
                        em área privada do grupo para reprocessamento futuro.
                    </p>
                </div>

                <div data-cte-import-summary hidden>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-1.5">
                        <span class="text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">Selecionados</span>
                        <span class="font-mono text-xs text-slate-500 tabular">
                            <span data-cte-import-count>0</span> de 20
                        </span>
                    </div>
                    <ul data-cte-import-list class="mt-1 max-h-56 divide-y divide-slate-100 overflow-auto"></ul>
                    <p data-cte-import-warning hidden class="mt-2 text-xs text-danger-700">
                        Selecione no máximo 20 arquivos por vez. Remova alguns para continuar.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                    <x-ui.link-button href="{{ route('cte.index') }}" variant="secondary">Cancelar</x-ui.link-button>
                    <x-ui.button type="submit" variant="primary" data-cte-import-submit>Importar CT-e</x-ui.button>
                </div>
            </form>
        </div>
    </div>
@endsection
