@extends('layouts.app')

@section('title', 'Assinatura | Frotika')

@section('content')
    <x-ui.page-header title="Assinatura e licenças" subtitle="Controle de trial e boletos das empresas do grupo">
        <x-slot:actions>
            @if ($canManageCompanyLicenses)
                <span class="rounded-md border border-brand-200 bg-brand-50 px-2 py-1 text-xs font-medium text-brand-700">
                    Gestão liberada para a empresa principal
                </span>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($currentLicense)
        <section class="mb-4 rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">Empresa ativa</p>
            <div class="mt-1 flex flex-wrap items-center gap-2">
                <p class="font-display text-lg font-semibold text-slate-900">
                    {{ $currentLicense->company?->getAttribute('trade_name') ?? 'Empresa sem nome' }}
                </p>
                @if ($currentLicense->is_primary)
                    <span
                        class="rounded-md border border-accent-200 bg-accent-50 px-2 py-0.5 text-2xs font-semibold text-accent-700">
                        Principal
                    </span>
                @endif
            </div>
            <p class="mt-1 text-sm text-slate-600">
                Status da licença: <span class="font-medium text-slate-900">{{ $currentLicense->status->label() }}</span>
                @if ($currentLicense->trial_ends_at)
                    · Trial até {{ Format::date($currentLicense->trial_ends_at) }}
                @endif
            </p>

            @php
                /** @var \App\Domain\Billing\Models\CompanyLicenseInvoice|null $currentInvoice */
                $currentInvoice = $currentLicense->latestInvoice;
            @endphp

            @if ($currentInvoice !== null && in_array($currentInvoice->status->value, ['pending', 'overdue'], true))
                <div class="mt-3 rounded-md border border-warning-200 bg-warning-50 px-3 py-2.5">
                    <p class="text-sm font-medium text-warning-700">
                        Boleto pendente da empresa ativa · vencimento {{ Format::date($currentInvoice->due_date) }}
                    </p>
                    <p class="mt-1 font-mono text-sm text-slate-900 tabular">
                        <span class="unit">R$</span> {{ Format::moneyDecimal($currentInvoice->amount_cents / 100) }}
                    </p>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                        @if ($currentInvoice->boleto_url)
                            <a href="{{ $currentInvoice->boleto_url }}" target="_blank" rel="noopener"
                                class="inline-flex items-center rounded-md border border-brand-300 px-2 py-1 font-medium text-brand-700 hover:bg-brand-50">
                                Abrir boleto
                            </a>
                        @endif
                        @if ($currentInvoice->boleto_pdf_url)
                            <a href="{{ $currentInvoice->boleto_pdf_url }}" target="_blank" rel="noopener"
                                class="inline-flex items-center rounded-md border border-slate-300 px-2 py-1 font-medium text-slate-700 hover:bg-slate-50">
                                PDF
                            </a>
                        @endif
                        @if ($currentInvoice->boleto_number)
                            <span class="font-mono text-xs text-slate-500 tabular">Linha:
                                {{ $currentInvoice->boleto_number }}</span>
                        @endif
                    </div>
                </div>
            @endif
        </section>
    @endif

    <section class="rounded-lg border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-4 py-2.5">
            <h2 class="font-display text-lg font-semibold text-slate-900">Licenças por empresa</h2>
            <p class="text-xs text-slate-500">Cada empresa possui trial de 7 dias e cobrança mensal por boleto.</p>
        </div>

        <div class="overflow-auto">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-slate-50">
                    <tr class="border-b border-slate-200">
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">
                            Empresa</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">
                            Status</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">
                            Trial</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">
                            Último boleto</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">
                            Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($licenses as $license)
                        @php
                            /** @var \App\Domain\Billing\Models\CompanyLicenseInvoice|null $invoice */
                            $invoice = $license->latestInvoice;
                        @endphp
                        <tr class="align-top border-b border-slate-100">
                            <td class="px-3 py-2.5">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="font-medium text-slate-900">{{ $license->company?->getAttribute('trade_name') ?? 'Empresa sem nome' }}</span>
                                    @if ($license->is_primary)
                                        <span
                                            class="rounded-md border border-accent-200 bg-accent-50 px-2 py-0.5 text-2xs font-semibold text-accent-700">
                                            Principal
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-2.5">
                                <span class="font-medium text-slate-700">{{ $license->status->label() }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-slate-600">
                                {{ Format::date($license->trial_starts_at) }}
                                @if ($license->trial_ends_at)
                                    → {{ Format::date($license->trial_ends_at) }}
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                @if ($invoice)
                                    <p class="font-mono text-sm text-slate-900 tabular">
                                        <span class="unit">R$</span>
                                        {{ Format::moneyDecimal($invoice->amount_cents / 100) }}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{ $invoice->status->label() }} · venc. {{ Format::date($invoice->due_date) }}
                                    </p>
                                    <div class="mt-1 flex flex-wrap gap-1.5 text-xs">
                                        @if ($invoice->boleto_url)
                                            <a href="{{ $invoice->boleto_url }}" target="_blank" rel="noopener"
                                                class="rounded border border-brand-300 px-2 py-0.5 font-medium text-brand-700 hover:bg-brand-50">
                                                Quitar boleto
                                            </a>
                                        @endif
                                        @if ($invoice->boleto_pdf_url)
                                            <a href="{{ $invoice->boleto_pdf_url }}" target="_blank" rel="noopener"
                                                class="rounded border border-slate-300 px-2 py-0.5 font-medium text-slate-700 hover:bg-slate-50">
                                                PDF
                                            </a>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-xs text-slate-500">Sem boleto lançado</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                @if ($canManageCompanyLicenses)
                                    <form method="POST"
                                        action="{{ route('billing.licenses.issue', ['license' => $license->getKey()]) }}"
                                        class="grid gap-1.5 rounded-md border border-slate-200 bg-slate-50 p-2.5">
                                        @csrf
                                        <label class="text-2xs font-medium text-slate-600"
                                            for="amount_cents_{{ $license->getKey() }}">Mensalidade (centavos)</label>
                                        <input id="amount_cents_{{ $license->getKey() }}" name="amount_cents"
                                            type="number" min="1"
                                            value="{{ $license->monthly_price_cents ?: $defaultMonthlyPriceCents }}"
                                            class="h-8 rounded border border-slate-300 px-2 text-sm text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                                            required />

                                        <label class="text-2xs font-medium text-slate-600"
                                            for="due_date_{{ $license->getKey() }}">Vencimento</label>
                                        <input id="due_date_{{ $license->getKey() }}" name="due_date" type="date"
                                            value="{{ now()->addDays(3)->toDateString() }}"
                                            class="h-8 rounded border border-slate-300 px-2 text-sm text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                                            required />

                                        <label class="text-2xs font-medium text-slate-600"
                                            for="reference_month_{{ $license->getKey() }}">Competência (AAAA-MM)</label>
                                        <input id="reference_month_{{ $license->getKey() }}" name="reference_month"
                                            type="month" value="{{ now()->format('Y-m') }}"
                                            class="h-8 rounded border border-slate-300 px-2 text-sm text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20" />

                                        <label class="text-2xs font-medium text-slate-600"
                                            for="boleto_number_{{ $license->getKey() }}">Linha digitável</label>
                                        <input id="boleto_number_{{ $license->getKey() }}" name="boleto_number"
                                            type="text" placeholder="Opcional"
                                            class="h-8 rounded border border-slate-300 px-2 text-sm text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20" />

                                        <label class="text-2xs font-medium text-slate-600"
                                            for="boleto_url_{{ $license->getKey() }}">URL do boleto</label>
                                        <input id="boleto_url_{{ $license->getKey() }}" name="boleto_url" type="url"
                                            placeholder="https://..."
                                            class="h-8 rounded border border-slate-300 px-2 text-sm text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20" />

                                        <label class="text-2xs font-medium text-slate-600"
                                            for="boleto_pdf_url_{{ $license->getKey() }}">URL do PDF</label>
                                        <input id="boleto_pdf_url_{{ $license->getKey() }}" name="boleto_pdf_url"
                                            type="url" placeholder="https://..."
                                            class="h-8 rounded border border-slate-300 px-2 text-sm text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20" />

                                        <x-ui.button type="submit" size="sm" class="mt-1">Lançar
                                            boleto</x-ui.button>
                                    </form>

                                    @if ($invoice && in_array($invoice->status->value, ['pending', 'overdue'], true))
                                        <form method="POST"
                                            action="{{ route('billing.licenses.mark-paid', ['invoice' => $invoice->getKey()]) }}"
                                            class="mt-2 grid gap-1.5 rounded-md border border-success-200 bg-success-50 p-2.5">
                                            @csrf
                                            <label class="text-2xs font-medium text-success-700"
                                                for="paid_at_{{ $invoice->getKey() }}">Data do pagamento</label>
                                            <input id="paid_at_{{ $invoice->getKey() }}" name="paid_at" type="date"
                                                value="{{ now()->toDateString() }}"
                                                class="h-8 rounded border border-success-200 px-2 text-sm text-slate-900 focus:border-success-500 focus:ring-2 focus:ring-success-500/20" />
                                            <label class="text-2xs font-medium text-success-700"
                                                for="paid_note_{{ $invoice->getKey() }}">Observação</label>
                                            <input id="paid_note_{{ $invoice->getKey() }}" name="paid_note"
                                                type="text" placeholder="Baixa manual conferida"
                                                class="h-8 rounded border border-success-200 px-2 text-sm text-slate-900 focus:border-success-500 focus:ring-2 focus:ring-success-500/20" />
                                            <x-ui.button type="submit" size="sm" variant="secondary">Confirmar
                                                pagamento</x-ui.button>
                                        </form>
                                    @endif
                                @else
                                    <span class="text-xs text-slate-500">Somente a empresa principal pode lançar ou baixar
                                        boletos.</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-6 text-center text-sm text-slate-500">
                                Nenhuma licença encontrada para o grupo atual.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
