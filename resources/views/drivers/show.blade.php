@extends('layouts.app')

@section('title', 'Motorista | Frotika')

@php
    use App\Domain\Fleet\Enums\DriverStatus;

    $statusChip = match ($driver->status) {
        DriverStatus::Active => 'border-success-300 bg-success-50 text-success-700',
        default => 'border-slate-300 bg-slate-50 text-slate-500',
    };

    $alert = $driver->cnhAlert();
    $days = $driver->cnhDaysToExpire();
@endphp

@section('content')
    <div class="mb-4 flex items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('drivers.index') }}" class="text-sm text-slate-500 hover:text-brand-700">Motoristas</a>
                <span class="text-slate-300">/</span>
                <h1 class="font-display text-xl font-semibold text-slate-900">{{ $driver->getAttribute('name') }}</h1>
            </div>
            <div class="mt-1">
                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-2xs font-semibold {{ $statusChip }}">{{ $driver->status->label() }}</span>
            </div>
        </div>

        @if ($canManage)
            <div class="flex items-center gap-2">
                <x-ui.link-button href="{{ route('drivers.edit', ['driver' => $driver->getKey()]) }}" variant="secondary">Editar</x-ui.link-button>
                <form method="POST" action="{{ route('drivers.destroy', ['driver' => $driver->getKey()]) }}"
                    onsubmit="return confirm('Remover este motorista?');">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="danger">Remover</x-ui.button>
                </form>
            </div>
        @endif
    </div>

    @if ($alert === 'expired')
        <div class="mb-4 rounded-lg border border-danger-300 bg-danger-50 px-4 py-3 text-sm text-danger-700">
            <strong class="font-semibold">CNH vencida</strong> em {{ Format::date($driver->getAttribute('cnh_expires_at')) }}. Regularize antes de liberar o motorista.
        </div>
    @elseif ($alert === 'expiring')
        <div class="mb-4 rounded-lg border border-warning-300 bg-warning-50 px-4 py-3 text-sm text-warning-700">
            <strong class="font-semibold">CNH vence em {{ $days }} {{ \Illuminate\Support\Str::plural('dia', $days) }}</strong> ({{ Format::date($driver->getAttribute('cnh_expires_at')) }}).
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Identificação</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Nome</dt>
                    <dd class="text-slate-900">{{ $driver->getAttribute('name') }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">CPF</dt>
                    <dd class="font-mono tabular text-slate-900">{{ $driver->getAttribute('cpf') ? Format::cpf($driver->getAttribute('cpf')) : '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Habilitação</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Número</dt>
                    <dd class="font-mono tabular text-slate-900">{{ $driver->getAttribute('cnh_number') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Categoria</dt>
                    <dd class="text-slate-900">{{ $driver->cnh_category?->label() ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Vencimento</dt>
                    <dd class="font-mono tabular text-slate-900">{{ $driver->getAttribute('cnh_expires_at') ? Format::date($driver->getAttribute('cnh_expires_at')) : '—' }}</dd>
                </div>
            </dl>
        </div>
    </div>

    @if ($driver->getAttribute('notes'))
        <div class="mt-4 rounded-lg border border-slate-200 bg-white p-4">
            <h2 class="mb-2 text-sm font-semibold text-slate-900">Observações</h2>
            <p class="whitespace-pre-line text-sm text-slate-700">{{ $driver->getAttribute('notes') }}</p>
        </div>
    @endif
@endsection
