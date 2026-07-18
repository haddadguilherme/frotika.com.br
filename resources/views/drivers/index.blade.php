@extends('layouts.app')

@section('title', 'Motoristas | Frotika')

@php
    use App\Domain\Fleet\Enums\DriverStatus;

    $statusChip = fn (DriverStatus $status) => match ($status) {
        DriverStatus::Active => 'border-success-300 bg-success-50 text-success-700',
        default => 'border-slate-300 bg-slate-50 text-slate-500',
    };

    $cnhBadge = function ($driver) {
        $alert = $driver->cnhAlert();
        $days = $driver->cnhDaysToExpire();
        if ($alert === 'expired') {
            return ['classes' => 'border-danger-300 bg-danger-50 text-danger-700', 'text' => 'CNH vencida'];
        }
        if ($alert === 'expiring') {
            return ['classes' => 'border-warning-300 bg-warning-50 text-warning-700', 'text' => 'Vence em '.$days.'d'];
        }
        return null;
    };
@endphp

@section('content')
    <div class="mb-4 flex items-start justify-between gap-4">
        <div>
            <h1 class="font-display text-xl font-semibold text-slate-900">Motoristas</h1>
            <p class="mt-0.5 text-sm text-slate-500">
                {{ $drivers->count() }} {{ \Illuminate\Support\Str::plural('motorista', $drivers->count()) }}
            </p>
        </div>
        @if ($canManage)
            <div class="hidden lg:block">
                <x-ui.link-button href="{{ route('drivers.create') }}" variant="primary">Novo motorista</x-ui.link-button>
            </div>
        @endif
    </div>

    <form method="GET" action="{{ route('drivers.index') }}"
        class="mb-3 grid gap-2 rounded-lg border border-slate-200 bg-white p-3 sm:grid-cols-2 lg:grid-cols-5">
        <input type="text" name="q" value="{{ $search }}" placeholder="Nome ou CPF"
            class="h-9 rounded-md border border-slate-300 bg-white px-2 text-sm text-slate-900 lg:col-span-2" />

        <select name="status" class="h-9 rounded-md border border-slate-300 bg-white px-2 text-sm text-slate-900">
            <option value="">Situação: todas</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected($statusFilter === $status)>{{ $status->label() }}</option>
            @endforeach
        </select>

        <label class="flex items-center gap-2 px-1 text-sm text-slate-700">
            <input type="checkbox" name="alerts" value="1" @checked($onlyAlerts)
                class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500/30" />
            Só CNH a vencer
        </label>

        <div class="flex gap-2">
            <x-ui.button type="submit">Filtrar</x-ui.button>
            <x-ui.link-button href="{{ route('drivers.index') }}" variant="secondary" class="justify-center">Limpar</x-ui.link-button>
        </div>
    </form>

    <div class="rounded-lg border border-slate-200 bg-white">
        <div class="hidden overflow-auto md:block">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-slate-50">
                    <tr class="border-b border-slate-200">
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Nome</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">CPF</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">CNH</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Vencimento</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Situação</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($drivers as $driver)
                        @php($badge = $cnhBadge($driver))
                        <tr class="h-9 cursor-pointer border-b border-slate-100 hover:bg-slate-50"
                            onclick="window.location='{{ route('drivers.show', ['driver' => $driver->getKey()]) }}'">
                            <td class="px-3 font-medium text-slate-900">{{ $driver->getAttribute('name') }}</td>
                            <td class="px-3 font-mono tabular text-slate-600">{{ $driver->getAttribute('cpf') ? Format::cpf($driver->getAttribute('cpf')) : '—' }}</td>
                            <td class="px-3 text-slate-600">
                                {{ $driver->cnh_category?->value ?? '—' }}
                                <span class="text-slate-400">{{ $driver->getAttribute('cnh_number') ? '· '.$driver->getAttribute('cnh_number') : '' }}</span>
                            </td>
                            <td class="px-3">
                                @if ($driver->getAttribute('cnh_expires_at'))
                                    <span class="font-mono tabular text-slate-600">{{ Format::date($driver->getAttribute('cnh_expires_at')) }}</span>
                                    @if ($badge)
                                        <span class="ml-1 inline-flex items-center rounded-full border px-2 py-0.5 text-2xs font-semibold {{ $badge['classes'] }}">{{ $badge['text'] }}</span>
                                    @endif
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-3">
                                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-2xs font-semibold {{ $statusChip($driver->status) }}">{{ $driver->status->label() }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="px-4 py-12 text-center">
                                    <p class="font-display text-lg font-semibold text-slate-900">Nenhum motorista encontrado.</p>
                                    <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">Cadastre os motoristas para vincular a viagens, abastecimentos e o controle de vencimento da CNH.</p>
                                    @if ($canManage)
                                        <div class="mt-4 flex justify-center">
                                            <x-ui.link-button href="{{ route('drivers.create') }}" variant="primary">Novo motorista</x-ui.link-button>
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
            @forelse ($drivers as $driver)
                @php($badge = $cnhBadge($driver))
                <a href="{{ route('drivers.show', ['driver' => $driver->getKey()]) }}" class="block px-4 py-3 active:bg-slate-50">
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-medium text-slate-900">{{ $driver->getAttribute('name') }}</span>
                        @if ($badge)
                            <span class="shrink-0 inline-flex items-center rounded-full border px-2 py-0.5 text-2xs font-semibold {{ $badge['classes'] }}">{{ $badge['text'] }}</span>
                        @endif
                    </div>
                    <div class="mt-0.5 flex items-center gap-2 text-xs text-slate-500">
                        <span class="font-mono tabular">{{ $driver->getAttribute('cpf') ? Format::cpf($driver->getAttribute('cpf')) : '—' }}</span>
                        @if ($driver->cnh_category)
                            <span>· CNH {{ $driver->cnh_category->value }}</span>
                        @endif
                    </div>
                </a>
            @empty
                <div class="px-4 py-12 text-center">
                    <p class="font-display text-lg font-semibold text-slate-900">Nenhum motorista encontrado.</p>
                </div>
            @endforelse
        </div>
    </div>

    @if ($canManage)
        <a href="{{ route('drivers.create') }}"
            class="fixed bottom-20 right-4 z-20 flex size-14 items-center justify-center rounded-full bg-brand-700 text-white shadow-overlay active:bg-brand-800 md:hidden"
            aria-label="Novo motorista">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path d="M12 5v14M5 12h14" stroke-linecap="round" />
            </svg>
        </a>
    @endif
@endsection
