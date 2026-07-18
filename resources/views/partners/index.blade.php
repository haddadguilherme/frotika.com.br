@extends('layouts.app')

@section('title', 'Parceiros | Frotika')

@section('content')
    <div class="mb-4 flex items-start justify-between gap-4">
        <div>
            <h1 class="font-display text-xl font-semibold text-slate-900">Parceiros comerciais</h1>
            <p class="mt-0.5 text-sm text-slate-500">
                {{ $partners->count() }} {{ \Illuminate\Support\Str::plural('parceiro', $partners->count()) }} cadastrado{{ $partners->count() === 1 ? '' : 's' }}
            </p>
        </div>

        @if ($canManage)
            <div class="hidden lg:block">
                <x-ui.link-button href="{{ route('partners.create') }}" variant="primary">Novo parceiro</x-ui.link-button>
            </div>
        @endif
    </div>

    <div class="rounded-lg border border-slate-200 bg-white">
        <div class="hidden overflow-auto md:block">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-slate-50">
                    <tr class="border-b border-slate-200">
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Parceiro</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Documento</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Tipo</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Cidade/UF</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($partners as $partner)
                        <tr class="h-9 border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-3">
                                <a href="{{ route('partners.show', ['partner' => $partner->getKey()]) }}"
                                    class="font-medium text-slate-900 hover:text-brand-700">{{ $partner->getAttribute('legal_name') }}</a>
                                @unless ($partner->getAttribute('active'))
                                    <span class="ml-1 inline-flex items-center rounded-full border border-slate-300 px-2 py-0.5 text-2xs font-semibold text-slate-500">Inativo</span>
                                @endunless
                            </td>
                            <td class="px-3 font-mono text-xs tabular text-slate-600">
                                @if ($partner->getAttribute('document'))
                                    {{ $partner->document_type === \App\Domain\Partners\Enums\BusinessPartnerDocumentType::Cpf ? Format::cpf($partner->getAttribute('document')) : Format::cnpj($partner->getAttribute('document')) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 text-slate-600">{{ $partner->kind->label() }}</td>
                            <td class="px-3 text-slate-600">{{ collect([$partner->getAttribute('city'), $partner->getAttribute('state')])->filter()->join('/') ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="px-4 py-12 text-center">
                                    <p class="font-display text-lg font-semibold text-slate-900">Nenhum parceiro cadastrado.</p>
                                    <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">
                                        Os parceiros são cadastrados automaticamente ao importar CT-e, ou manualmente para postos e oficinas.
                                    </p>
                                    @if ($canManage)
                                        <div class="mt-4 flex justify-center">
                                            <x-ui.link-button href="{{ route('partners.create') }}" variant="primary">Novo parceiro</x-ui.link-button>
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
            @forelse ($partners as $partner)
                <a href="{{ route('partners.show', ['partner' => $partner->getKey()]) }}" class="block px-4 py-3 active:bg-slate-50">
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-medium text-slate-900">{{ $partner->getAttribute('legal_name') }}</span>
                        <span class="text-xs text-slate-500">{{ $partner->kind->label() }}</span>
                    </div>
                    <div class="mt-0.5 font-mono text-xs tabular text-slate-500">
                        @if ($partner->getAttribute('document'))
                            {{ $partner->document_type === \App\Domain\Partners\Enums\BusinessPartnerDocumentType::Cpf ? Format::cpf($partner->getAttribute('document')) : Format::cnpj($partner->getAttribute('document')) }}
                        @else
                            sem documento
                        @endif
                    </div>
                </a>
            @empty
                <div class="px-4 py-12 text-center">
                    <p class="font-display text-lg font-semibold text-slate-900">Nenhum parceiro cadastrado.</p>
                </div>
            @endforelse
        </div>
    </div>

    @if ($canManage)
        <a href="{{ route('partners.create') }}"
            class="fixed bottom-20 right-4 z-20 flex size-14 items-center justify-center rounded-full bg-brand-700 text-white active:bg-brand-800 md:hidden shadow-overlay"
            aria-label="Novo parceiro">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path d="M12 5v14M5 12h14" stroke-linecap="round" />
            </svg>
        </a>
    @endif
@endsection
