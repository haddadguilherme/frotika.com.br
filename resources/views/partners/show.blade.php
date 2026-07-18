@extends('layouts.app')

@section('title', $partner->getAttribute('legal_name') . ' | Frotika')

@section('content')
    <div class="mb-4 flex items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('partners.index') }}" class="text-sm text-slate-500 hover:text-brand-700">Parceiros</a>
                <span class="text-slate-300">/</span>
                <h1 class="font-display text-xl font-semibold text-slate-900">{{ $partner->getAttribute('legal_name') }}</h1>
            </div>
            <p class="mt-1 text-sm text-slate-500">{{ $partner->kind->label() }}</p>
        </div>

        @if ($canManage)
            <div class="flex items-center gap-2">
                <x-ui.link-button href="{{ route('partners.edit', ['partner' => $partner->getKey()]) }}" variant="secondary">Editar</x-ui.link-button>
                <form method="POST" action="{{ route('partners.destroy', ['partner' => $partner->getKey()]) }}"
                    onsubmit="return confirm('Desativar este parceiro?');">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="danger">Desativar</x-ui.button>
                </form>
            </div>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Identificação</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Documento</dt>
                    <dd class="font-mono tabular text-slate-900">
                        @if ($partner->getAttribute('document'))
                            {{ $partner->document_type === \App\Domain\Partners\Enums\BusinessPartnerDocumentType::Cpf ? Format::cpf($partner->getAttribute('document')) : Format::cnpj($partner->getAttribute('document')) }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Nome fantasia</dt>
                    <dd class="text-slate-900">{{ $partner->getAttribute('trade_name') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Inscrição estadual</dt>
                    <dd class="text-slate-900">{{ $partner->getAttribute('state_registration') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">% do frete</dt>
                    <dd class="font-mono tabular text-slate-900">
                        {{ $partner->getAttribute('default_freight_share_percent') !== null ? Format::percent($partner->getAttribute('default_freight_share_percent')) : '—' }}
                    </dd>
                </div>
            </dl>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Contato e endereço</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Telefone</dt>
                    <dd class="text-slate-900">{{ $partner->getAttribute('phone') ? Format::phone($partner->getAttribute('phone')) : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">E-mail</dt>
                    <dd class="text-slate-900">{{ $partner->getAttribute('email') ?? '—' }}</dd>
                </div>
                <div class="col-span-2">
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Endereço</dt>
                    <dd class="text-slate-900">
                        {{ collect([
                            $partner->getAttribute('street'),
                            $partner->getAttribute('number'),
                            $partner->getAttribute('district'),
                            collect([$partner->getAttribute('city'), $partner->getAttribute('state')])->filter()->join('/'),
                        ])->filter()->join(', ') ?: '—' }}
                    </dd>
                </div>
            </dl>
        </div>
    </div>

    @if ($partner->getAttribute('notes'))
        <div class="mt-4 rounded-lg border border-slate-200 bg-white p-4">
            <h2 class="mb-2 text-sm font-semibold text-slate-900">Observações</h2>
            <p class="whitespace-pre-line text-sm text-slate-700">{{ $partner->getAttribute('notes') }}</p>
        </div>
    @endif
@endsection
