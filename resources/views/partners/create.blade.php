@extends('layouts.app')

@section('title', 'Novo parceiro | Frotika')

@section('content')
    <div class="mx-auto max-w-3xl">
        <x-ui.page-header title="Novo parceiro" subtitle="Cadastre uma contratante, cliente, posto ou oficina.">
            <x-slot:actions>
                <x-ui.link-button href="{{ route('partners.index') }}" variant="secondary">Voltar</x-ui.link-button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.card class="border-slate-200 bg-white">
            <p class="text-sm text-slate-600">
                Informe o CNPJ e buscamos a razão social e o endereço na Receita. Os parceiros também são cadastrados
                automaticamente ao importar CT-e — aqui você complementa postos, oficinas e o percentual do frete das
                contratantes.
            </p>

            <form method="POST" action="{{ route('partners.store') }}" class="mt-6">
                @csrf
                @include('partners._form', ['partner' => null])

                <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-4">
                    <x-ui.link-button href="{{ route('partners.index') }}" variant="secondary">Cancelar</x-ui.link-button>
                    <x-ui.button type="submit">Cadastrar parceiro</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
