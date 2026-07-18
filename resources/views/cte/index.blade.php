@extends('layouts.app')

@section('title', 'CT-e | Frotika')

@section('content')
    <div class="mb-4 flex items-start justify-between gap-4">
        <div>
            <h1 class="font-display text-xl font-semibold text-slate-900">CT-e</h1>
            <p class="mt-0.5 text-sm text-slate-500">
                {{ $documents->count() }} {{ \Illuminate\Support\Str::plural('documento', $documents->count()) }} importado{{ $documents->count() === 1 ? '' : 's' }}
            </p>
        </div>

        @if ($canImport)
            <div class="hidden lg:block">
                <x-ui.link-button href="{{ route('cte.import') }}" variant="primary">Importar CT-e</x-ui.link-button>
            </div>
        @endif
    </div>

    <div class="rounded-lg border border-slate-200 bg-white">
        <div class="hidden overflow-auto md:block">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-slate-50">
                    <tr class="border-b border-slate-200">
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">CT-e</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Emissão</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Emitente</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Veículo</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Trecho</th>
                        <th class="px-3 py-2 text-right text-2xs font-semibold uppercase tracking-wide text-slate-500">vTPrest</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        <tr class="h-9 border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-3">
                                <a href="{{ route('cte.show', ['cte' => $document->getKey()]) }}"
                                    class="font-medium text-slate-900 hover:text-brand-700">
                                    {{ $document->getAttribute('number') }}/{{ $document->getAttribute('series') }}
                                </a>
                            </td>
                            <td class="px-3 text-slate-600">{{ Format::date($document->issued_at) }}</td>
                            <td class="px-3 text-slate-600">{{ $document->getAttribute('issuer_name') ?? '—' }}</td>
                            <td class="px-3 font-mono text-xs text-slate-600 tabular">
                                {{ $document->vehicle?->getAttribute('plate') ? Format::plate($document->vehicle->getAttribute('plate')) : '—' }}
                            </td>
                            <td class="px-3 text-slate-600">
                                {{ collect([$document->getAttribute('origin_state'), $document->getAttribute('destination_state')])->filter()->join(' → ') ?: '—' }}
                            </td>
                            <td class="px-3 text-right font-mono tabular text-slate-900">
                                <span class="unit">R$</span> {{ Format::moneyDecimal($document->getAttribute('total_value_cents') / 100) }}
                            </td>
                            <td class="px-3">
                                <span class="text-xs text-slate-500">{{ $document->status->label() }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="px-4 py-12 text-center">
                                    <p class="font-display text-lg font-semibold text-slate-900">Nenhum CT-e importado.</p>
                                    <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">
                                        Envie o XML dos seus CT-e para o sistema cadastrar os parceiros, vincular o veículo e
                                        lançar a receita automaticamente.
                                    </p>
                                    @if ($canImport)
                                        <div class="mt-4 flex justify-center">
                                            <x-ui.link-button href="{{ route('cte.import') }}" variant="primary">Importar CT-e</x-ui.link-button>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="divide-y divide-slate-100 md:hidden">
            @forelse ($documents as $document)
                <a href="{{ route('cte.show', ['cte' => $document->getKey()]) }}" class="block px-4 py-3 active:bg-slate-50">
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-medium text-slate-900">CT-e {{ $document->getAttribute('number') }}/{{ $document->getAttribute('series') }}</span>
                        <span class="font-mono text-sm tabular text-slate-900">R$ {{ Format::moneyDecimal($document->getAttribute('total_value_cents') / 100) }}</span>
                    </div>
                    <div class="mt-0.5 text-xs text-slate-500">{{ $document->getAttribute('issuer_name') ?? '—' }} · {{ Format::date($document->issued_at) }}</div>
                </a>
            @empty
                <div class="px-4 py-12 text-center">
                    <p class="font-display text-lg font-semibold text-slate-900">Nenhum CT-e importado.</p>
                </div>
            @endforelse
        </div>
    </div>

    @if ($canImport)
        <a href="{{ route('cte.import') }}"
            class="fixed bottom-20 right-4 z-20 flex size-14 items-center justify-center rounded-full bg-brand-700 text-white active:bg-brand-800 md:hidden shadow-overlay"
            aria-label="Importar CT-e">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path d="M12 5v14M5 12h14" stroke-linecap="round" />
            </svg>
        </a>
    @endif
@endsection
