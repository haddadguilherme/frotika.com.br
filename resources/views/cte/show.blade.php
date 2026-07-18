@extends('layouts.app')

@section('title', 'CT-e ' . $document->getAttribute('number') . ' | Frotika')

@section('content')
    <div class="mb-4 flex items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('cte.index') }}" class="text-sm text-slate-500 hover:text-brand-700">CT-e</a>
                <span class="text-slate-300">/</span>
                <h1 class="font-display text-xl font-semibold text-slate-900">
                    {{ $document->getAttribute('number') }}/{{ $document->getAttribute('series') }}
                </h1>
                <span class="inline-flex items-center rounded-full border border-slate-300 px-2 py-0.5 text-2xs font-semibold uppercase tracking-widest text-slate-600">
                    {{ $document->status->label() }}
                </span>
            </div>
            <p class="mt-1 font-mono text-xs tabular text-slate-500">{{ Format::cteKey($document->getAttribute('access_key')) }}</p>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <h2 class="mb-3 text-sm font-semibold text-slate-900">Dados do documento</h2>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-2xs uppercase tracking-wide text-slate-400">Emissão</dt>
                        <dd class="text-slate-900">{{ Format::dateTime($document->issued_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-2xs uppercase tracking-wide text-slate-400">Tipo</dt>
                        <dd class="text-slate-900">{{ $document->cte_type->label() }}</dd>
                    </div>
                    <div>
                        <dt class="text-2xs uppercase tracking-wide text-slate-400">Serviço</dt>
                        <dd class="text-slate-900">{{ $document->service_type->label() }}</dd>
                    </div>
                    <div>
                        <dt class="text-2xs uppercase tracking-wide text-slate-400">CFOP</dt>
                        <dd class="font-mono tabular text-slate-900">{{ $document->getAttribute('cfop') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-2xs uppercase tracking-wide text-slate-400">Tomador</dt>
                        <dd class="text-slate-900">{{ $document->taker_role?->label() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-2xs uppercase tracking-wide text-slate-400">Protocolo</dt>
                        <dd class="font-mono tabular text-slate-900">{{ $document->getAttribute('protocol_number') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-2xs uppercase tracking-wide text-slate-400">Origem</dt>
                        <dd class="text-slate-900">{{ collect([$document->getAttribute('origin_city'), $document->getAttribute('origin_state')])->filter()->join('/') ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-2xs uppercase tracking-wide text-slate-400">Destino</dt>
                        <dd class="text-slate-900">{{ collect([$document->getAttribute('destination_city'), $document->getAttribute('destination_state')])->filter()->join('/') ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-2xs uppercase tracking-wide text-slate-400">RNTRC</dt>
                        <dd class="font-mono tabular text-slate-900">{{ $document->getAttribute('rntrc') ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <h2 class="mb-3 text-sm font-semibold text-slate-900">Parceiros</h2>
                <table class="w-full text-sm">
                    <tbody>
                        @foreach ($document->partners as $partner)
                            <tr class="border-b border-slate-100 last:border-0">
                                <td class="py-1.5 pr-3 text-2xs uppercase tracking-wide text-slate-400">
                                    {{ \App\Domain\Trips\Enums\CtePartyRole::from($partner->getAttribute('pivot')->getAttribute('role'))->label() }}
                                </td>
                                <td class="py-1.5 pr-3">
                                    <a href="{{ route('partners.show', ['partner' => $partner->getKey()]) }}"
                                        class="font-medium text-slate-900 hover:text-brand-700">{{ $partner->getAttribute('legal_name') }}</a>
                                </td>
                                <td class="py-1.5 text-right font-mono text-xs tabular text-slate-500">
                                    @if ($partner->getAttribute('document'))
                                        {{ $partner->document_type === \App\Domain\Partners\Enums\BusinessPartnerDocumentType::Cpf ? Format::cpf($partner->getAttribute('document')) : Format::cnpj($partner->getAttribute('document')) }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <h2 class="mb-3 text-sm font-semibold text-slate-900">Carga e veículo</h2>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <dt class="text-2xs uppercase tracking-wide text-slate-400">Produto predominante</dt>
                        <dd class="text-slate-900">{{ $document->getAttribute('cargo_description') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-2xs uppercase tracking-wide text-slate-400">Peso</dt>
                        <dd class="font-mono tabular text-slate-900">{{ $document->getAttribute('cargo_weight_kg') ? Format::km($document->getAttribute('cargo_weight_kg')) : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-2xs uppercase tracking-wide text-slate-400">Cavalo</dt>
                        <dd class="font-mono tabular text-slate-900">
                            @if ($document->vehicle)
                                <a href="{{ route('vehicles.show', ['vehicle' => $document->vehicle->getKey()]) }}"
                                    class="hover:text-brand-700">{{ Format::plate($document->vehicle->getAttribute('plate')) }}</a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-2xs uppercase tracking-wide text-slate-400">Carreta</dt>
                        <dd class="font-mono tabular text-slate-900">
                            @if ($document->trailer)
                                <a href="{{ route('vehicles.show', ['vehicle' => $document->trailer->getKey()]) }}"
                                    class="hover:text-brand-700">{{ Format::plate($document->trailer->getAttribute('plate')) }}</a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-2xs uppercase tracking-wide text-slate-400">Motorista</dt>
                        <dd class="text-slate-900">{{ $document->getAttribute('driver_name') ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <h2 class="mb-3 text-sm font-semibold text-slate-900">Valores</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">vTPrest</dt>
                        <dd class="font-mono tabular text-slate-900">R$ {{ Format::moneyDecimal($document->getAttribute('total_value_cents') / 100) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">vRec</dt>
                        <dd class="font-mono tabular text-slate-900">R$ {{ Format::moneyDecimal($document->getAttribute('receivable_value_cents') / 100) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">ICMS</dt>
                        <dd class="font-mono tabular text-slate-900">R$ {{ Format::moneyDecimal($document->getAttribute('icms_value_cents') / 100) }}</dd>
                    </div>
                    <div class="flex items-center justify-between border-t border-slate-100 pt-2">
                        <dt class="text-slate-500">Repasse ao agregado</dt>
                        <dd class="font-mono tabular text-slate-900">{{ Format::percent($document->getAttribute('applied_share_percent')) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <h2 class="mb-3 text-sm font-semibold text-slate-900">Lançamento no fluxo</h2>
                @if ($entry)
                    <p class="font-mono text-lg tabular text-slate-900">R$ {{ Format::moneyDecimal($entry->getAttribute('amount_cents') / 100) }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $entry->status->label() }} · receita a receber</p>
                    <dl class="mt-3 space-y-1.5 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-500">Competência</dt>
                            <dd class="text-slate-900">{{ Format::date($entry->getAttribute('competence_date')) }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-500">Vencimento</dt>
                            <dd class="text-slate-900">{{ Format::date($entry->getAttribute('due_date')) }}</dd>
                        </div>
                    </dl>
                @else
                    <p class="text-sm text-slate-500">Sem lançamento financeiro para este CT-e.</p>
                @endif
            </div>
        </div>
    </div>
@endsection
