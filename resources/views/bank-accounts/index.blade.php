@extends('layouts.app')

@section('title', 'Contas bancárias | Frotika')

@section('content')
    <div class="mb-4 flex items-start justify-between gap-4">
        <div>
            <h1 class="font-display text-xl font-semibold text-slate-900">Contas bancárias</h1>
            <p class="mt-0.5 text-sm text-slate-500">
                {{ $accounts->count() }} {{ \Illuminate\Support\Str::plural('conta', $accounts->count()) }}
            </p>
        </div>

        @if ($canManage)
            <div class="hidden lg:block">
                <x-ui.link-button href="{{ route('bank-accounts.create') }}" variant="primary">Nova conta</x-ui.link-button>
            </div>
        @endif
    </div>

    <div class="rounded-lg border border-slate-200 bg-white">
        <div class="hidden overflow-auto md:block">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-slate-50">
                    <tr class="border-b border-slate-200">
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Conta</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Tipo</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Dados</th>
                        <th class="px-3 py-2 text-right text-2xs font-semibold uppercase tracking-wide text-slate-500">Saldo atual</th>
                        <th class="px-3 py-2 text-right text-2xs font-semibold uppercase tracking-wide text-slate-500"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accounts as $account)
                        <tr class="h-9 border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-3">
                                <span class="font-medium text-slate-900">{{ $account->getAttribute('name') }}</span>
                                @if ($account->getAttribute('is_default'))
                                    <span class="ml-1 inline-flex items-center rounded-full border border-brand-300 bg-brand-50 px-2 py-0.5 text-2xs font-semibold text-brand-700">Padrão</span>
                                @endif
                            </td>
                            <td class="px-3 text-slate-600">{{ $account->type->label() }}</td>
                            <td class="px-3 font-mono text-xs tabular text-slate-500">{{ collect([$account->getAttribute('bank_code'), $account->getAttribute('agency'), $account->getAttribute('number')])->filter()->join(' · ') ?: '—' }}</td>
                            <td @class([
                                'px-3 text-right font-mono tabular',
                                'text-danger-700' => (int) $account->getAttribute('current_balance_cents') < 0,
                                'text-slate-900' => (int) $account->getAttribute('current_balance_cents') >= 0,
                            ])>{{ Format::money((int) $account->getAttribute('current_balance_cents')) }}</td>
                            <td class="px-3 text-right">
                                @if ($canManage)
                                    <a href="{{ route('bank-accounts.edit', ['account' => $account->getKey()]) }}"
                                        class="text-sm text-brand-700 hover:text-brand-800">Editar</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="px-4 py-12 text-center">
                                    <p class="font-display text-lg font-semibold text-slate-900">Nenhuma conta cadastrada.</p>
                                    @if ($canManage)
                                        <div class="mt-4 flex justify-center">
                                            <x-ui.link-button href="{{ route('bank-accounts.create') }}" variant="primary">Nova conta</x-ui.link-button>
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
            @forelse ($accounts as $account)
                <div class="flex items-center justify-between gap-3 px-4 py-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-slate-900">{{ $account->getAttribute('name') }}</span>
                            @if ($account->getAttribute('is_default'))
                                <span class="inline-flex items-center rounded-full border border-brand-300 bg-brand-50 px-2 py-0.5 text-2xs font-semibold text-brand-700">Padrão</span>
                            @endif
                        </div>
                        <span class="text-xs text-slate-500">{{ $account->type->label() }}</span>
                    </div>
                    <div class="text-right">
                        <div @class([
                            'font-mono tabular text-sm',
                            'text-danger-700' => (int) $account->getAttribute('current_balance_cents') < 0,
                            'text-slate-900' => (int) $account->getAttribute('current_balance_cents') >= 0,
                        ])>{{ Format::money((int) $account->getAttribute('current_balance_cents')) }}</div>
                        @if ($canManage)
                            <a href="{{ route('bank-accounts.edit', ['account' => $account->getKey()]) }}" class="text-xs text-brand-700">Editar</a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-4 py-12 text-center">
                    <p class="font-display text-lg font-semibold text-slate-900">Nenhuma conta cadastrada.</p>
                </div>
            @endforelse
        </div>
    </div>
@endsection
