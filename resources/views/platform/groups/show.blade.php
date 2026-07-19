@extends('platform.layout')

@section('title', $group->name . ' | Administração Frotika')

@section('content')
    <x-ui.page-header title="{{ $group->name }}" subtitle="Licença, faturas, empresas e usuários do grupo">
        <x-slot:actions>
            <a href="{{ route('platform.groups.index') }}"
                class="inline-flex h-9 items-center rounded-md border border-slate-300 px-3 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Voltar
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <section class="mb-4 rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">Responsável</p>
        <p class="mt-1 text-sm text-slate-900">
            {{ $group->owner?->name ?? '—' }}
            @if ($group->owner?->email)
                <span class="text-slate-500">· {{ $group->owner->email }}</span>
            @endif
        </p>
    </section>

    <section class="mb-4 rounded-lg border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-4 py-2.5">
            <h2 class="font-display text-lg font-semibold text-slate-900">Licença do grupo</h2>
            <p class="text-xs text-slate-500">Uma licença cobre todas as empresas do grupo. Lance o boleto mensal e dê
                baixa manual no pagamento.</p>
        </div>

        @if ($license === null)
            <p class="px-4 py-6 text-center text-sm text-slate-500">Este grupo ainda não possui licença.</p>
        @else
            @php($invoice = $license->latestInvoice)
            <div class="grid gap-4 p-4 lg:grid-cols-2">
                <div class="space-y-3">
                    <div>
                        <p class="text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">Status</p>
                        <p class="mt-0.5 font-medium text-slate-900">{{ $license->status->label() }}</p>
                        @if ($license->trial_ends_at)
                            <p class="text-xs text-slate-500">Trial até {{ Format::date($license->trial_ends_at) }}</p>
                        @endif
                    </div>

                    <div>
                        <p class="text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">Último boleto</p>
                        @if ($invoice)
                            <p class="mt-0.5 font-mono text-sm text-slate-900 tabular">
                                <span class="unit">R$</span> {{ Format::moneyDecimal($invoice->amount_cents / 100) }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ $invoice->status->label() }} · venc. {{ Format::date($invoice->due_date) }}
                            </p>
                            <div class="mt-1 flex flex-wrap gap-1.5 text-xs">
                                @if ($invoice->boleto_url)
                                    <a href="{{ $invoice->boleto_url }}" target="_blank" rel="noopener"
                                        class="rounded border border-brand-300 px-2 py-0.5 font-medium text-brand-700 hover:bg-brand-50">
                                        Abrir boleto
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
                            <p class="mt-0.5 text-xs text-slate-500">Sem boleto lançado</p>
                        @endif
                    </div>
                </div>

                <div>
                    <form method="POST" action="{{ route('platform.licenses.issue', ['license' => $license->getKey()]) }}"
                        enctype="multipart/form-data"
                        class="grid gap-1.5 rounded-md border border-slate-200 bg-slate-50 p-2.5">
                        @csrf
                        <label class="text-2xs font-medium text-slate-600" for="amount_reais">Mensalidade (R$)</label>
                        <input id="amount_reais" name="amount_reais" type="text" inputmode="decimal" placeholder="99,90"
                            value="{{ old('amount_reais', Format::moneyDecimal(($license->monthly_price_cents ?: $defaultMonthlyPriceCents) / 100)) }}"
                            class="h-8 rounded border border-slate-300 px-2 font-mono text-sm text-slate-900 tabular focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                            required />

                        <label class="text-2xs font-medium text-slate-600" for="due_date">Vencimento</label>
                        <input id="due_date" name="due_date" type="date"
                            value="{{ old('due_date', now()->addDays(3)->toDateString()) }}"
                            class="h-8 rounded border border-slate-300 px-2 text-sm text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                            required />

                        <label class="text-2xs font-medium text-slate-600" for="reference_month">Competência
                            (AAAA-MM)</label>
                        <input id="reference_month" name="reference_month" type="month"
                            value="{{ old('reference_month', now()->format('Y-m')) }}"
                            class="h-8 rounded border border-slate-300 px-2 text-sm text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20" />

                        <label class="text-2xs font-medium text-slate-600" for="boleto_number">Linha digitável</label>
                        <input id="boleto_number" name="boleto_number" type="text" placeholder="Opcional"
                            value="{{ old('boleto_number') }}"
                            class="h-8 rounded border border-slate-300 px-2 text-sm text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20" />

                        <label class="text-2xs font-medium text-slate-600" for="boleto_file">Anexo do boleto</label>
                        <input id="boleto_file" name="boleto_file" type="file" accept=".pdf,.jpg,.jpeg,.png"
                            class="h-8 rounded border border-slate-300 px-2 text-xs text-slate-700 file:mr-2 file:border-0 file:bg-slate-200 file:px-2 file:py-1 file:text-2xs file:font-medium file:text-slate-700 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20" />

                        <x-ui.button type="submit" size="sm" class="mt-1">Lançar boleto</x-ui.button>
                    </form>

                    @if ($invoice && in_array($invoice->status->value, ['pending', 'overdue'], true))
                        <form method="POST"
                            action="{{ route('platform.invoices.mark-paid', ['invoice' => $invoice->getKey()]) }}"
                            class="mt-2 grid gap-1.5 rounded-md border border-success-200 bg-success-50 p-2.5">
                            @csrf
                            <label class="text-2xs font-medium text-success-700" for="paid_at">Data do pagamento</label>
                            <input id="paid_at" name="paid_at" type="date" value="{{ now()->toDateString() }}"
                                class="h-8 rounded border border-success-200 px-2 text-sm text-slate-900 focus:border-success-500 focus:ring-2 focus:ring-success-500/20" />
                            <label class="text-2xs font-medium text-success-700" for="paid_note">Observação</label>
                            <input id="paid_note" name="paid_note" type="text" placeholder="Baixa manual conferida"
                                class="h-8 rounded border border-success-200 px-2 text-sm text-slate-900 focus:border-success-500 focus:ring-2 focus:ring-success-500/20" />
                            <x-ui.button type="submit" size="sm" variant="secondary">Confirmar pagamento</x-ui.button>
                        </form>
                    @endif
                </div>
            </div>
        @endif
    </section>

    <section class="mb-4 rounded-lg border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-4 py-2.5">
            <h2 class="font-display text-lg font-semibold text-slate-900">Empresas do grupo</h2>
        </div>
        <div class="overflow-auto">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-slate-50">
                    <tr class="border-b border-slate-200">
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">
                            Empresa</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">
                            CNPJ</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">
                            Cidade/UF</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($companies as $company)
                        <tr class="h-9 border-b border-slate-100">
                            <td class="px-3 py-2 font-medium text-slate-900">
                                {{ $company->getAttribute('trade_name') }}
                                @if ((int) $group->primary_company_id === $company->getKey())
                                    <span
                                        class="ml-1 rounded-md border border-accent-200 bg-accent-50 px-1.5 py-0.5 text-2xs font-semibold text-accent-700">Principal</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 font-mono text-xs text-slate-600 tabular">
                                {{ Format::cnpj($company->getAttribute('cnpj')) }}</td>
                            <td class="px-3 py-2 text-slate-600">
                                {{ collect([$company->getAttribute('city'), $company->getAttribute('state')])->filter()->join('/') ?:'—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-3 py-6 text-center text-sm text-slate-500">
                                Nenhuma empresa no grupo.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-4 py-2.5">
            <h2 class="font-display text-lg font-semibold text-slate-900">Usuários do grupo</h2>
        </div>
        <div class="overflow-auto">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-slate-50">
                    <tr class="border-b border-slate-200">
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">
                            Nome</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">
                            E-mail</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">
                            Papel</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $member)
                        <tr class="h-9 border-b border-slate-100">
                            <td class="px-3 py-2 font-medium text-slate-900">{{ $member->name }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $member->email }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $member->pivot->role ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-3 py-6 text-center text-sm text-slate-500">
                                Nenhum usuário no grupo.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
