@extends('layouts.app')

@section('title', Format::plate($vehicle->getAttribute('plate')) . ' | Frotika')

@php
    $statusChip = match ($vehicle->status) {
        \App\Domain\Fleet\Enums\VehicleStatus::Active => 'border-success-300 bg-success-50 text-success-700',
        \App\Domain\Fleet\Enums\VehicleStatus::Maintenance => 'border-warning-300 bg-warning-50 text-warning-700',
        default => 'border-slate-300 bg-slate-50 text-slate-500',
    };

    $showsBodyAndVolume = $vehicle->type !== \App\Domain\Fleet\Enums\VehicleType::Tractor;
    $capacityM3 = (float) ($vehicle->getAttribute('capacity_m3') ?? 0);
    $extraSpecs = array_filter([
        [
            'label' => 'Número do motor',
            'value' => $vehicle->getAttribute('engine_number'),
            'class' => 'font-mono tabular text-slate-900',
        ],
        [
            'label' => 'Distância entre eixos',
            'value' => $vehicle->getAttribute('axle_distance_m') !== null
                ? rtrim(rtrim((string) $vehicle->getAttribute('axle_distance_m'), '0'), '.').' m'
                : null,
            'class' => 'font-mono tabular text-slate-900',
        ],
        [
            'label' => 'Quantidade de pneus',
            'value' => $vehicle->getAttribute('tire_count'),
            'class' => 'font-mono tabular text-slate-900',
        ],
        [
            'label' => 'Medida dos pneus',
            'value' => $vehicle->getAttribute('tire_size'),
            'class' => 'font-mono tabular text-slate-900',
        ],
    ], static fn (array $item): bool => $item['value'] !== null && trim((string) $item['value']) !== '');

    $isFinanced = (bool) $vehicle->getAttribute('is_financed');
    $propertyDetails = array_filter([
        [
            'label' => 'Tipo de financiamento',
            'value' => $vehicle->financing_type?->label(),
            'class' => 'text-slate-900',
        ],
        [
            'label' => 'Credor',
            'value' => $vehicle->getAttribute('creditor_name'),
            'class' => 'text-slate-900',
        ],
    ], static fn (array $item): bool => $item['value'] !== null && trim((string) $item['value']) !== '');
    $dueFields = \App\Domain\Fleet\Models\Vehicle::documentDueFields();

    $badgeForDueField = function (string $field) use ($vehicle): ?array {
        $alert = $vehicle->documentAlert($field);
        $days = $vehicle->documentDaysToExpire($field);

        if ($alert === 'expired') {
            return [
                'classes' => 'border-danger-300 bg-danger-50 text-danger-700',
                'text' => 'Vencido',
            ];
        }

        if ($alert === 'expiring' && $days !== null) {
            return [
                'classes' => 'border-warning-300 bg-warning-50 text-warning-700',
                'text' => 'Vence em '.$days.'d',
            ];
        }

        return null;
    };
@endphp

@section('content')
    <div class="mb-4 flex items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('vehicles.index') }}" class="text-sm text-slate-500 hover:text-brand-700">Veículos</a>
                <span class="text-slate-300">/</span>
                <h1 class="font-display text-xl font-semibold tabular text-slate-900">
                    {{ Format::plate($vehicle->getAttribute('plate')) }}</h1>
            </div>
            <div class="mt-1 flex items-center gap-2">
                <p class="text-sm text-slate-500">{{ $vehicle->type->label() }}</p>
                <span
                    class="inline-flex items-center rounded-full border px-2 py-0.5 text-2xs font-semibold {{ $statusChip }}">{{ $vehicle->status->label() }}</span>
                @if ($vehicle->getAttribute('provisioned'))
                    <span
                        class="inline-flex items-center rounded-full border border-warning-300 bg-warning-50 px-2 py-0.5 text-2xs font-semibold text-warning-700">Cadastro incompleto</span>
                @endif
            </div>
        </div>

        @if ($canManage)
            <div class="flex items-center gap-2">
                <x-ui.link-button href="{{ route('vehicles.edit', ['vehicle' => $vehicle->getKey()]) }}"
                    variant="secondary">Editar</x-ui.link-button>
                <form method="POST" action="{{ route('vehicles.destroy', ['vehicle' => $vehicle->getKey()]) }}"
                    onsubmit="return confirm('Desativar este veículo?');">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="danger">Desativar</x-ui.button>
                </form>
            </div>
        @endif
    </div>

    @if ($vehicle->getAttribute('provisioned'))
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-md border border-warning-300 bg-warning-50 px-4 py-3">
            <p class="text-sm font-medium text-warning-700">
                Este veículo foi criado a partir do CT-e e ainda precisa de confirmação dos dados.
            </p>
            @if ($canManage)
                <x-ui.link-button href="{{ route('vehicles.edit', ['vehicle' => $vehicle->getKey()]) }}" variant="primary">Completar cadastro</x-ui.link-button>
            @endif
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Identificação</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Marca / Modelo</dt>
                    <dd class="text-slate-900">
                        {{ collect([$vehicle->getAttribute('brand'), $vehicle->getAttribute('model')])->filter()->join(' ') ?:'—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Ano fab. / modelo</dt>
                    <dd class="font-mono tabular text-slate-900">
                        {{ collect([$vehicle->getAttribute('year_manufacture'), $vehicle->getAttribute('year_model')])->filter()->join(' / ') ?:'—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">RNTRC</dt>
                    <dd class="font-mono tabular text-slate-900">{{ $vehicle->getAttribute('rntrc') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">RENAVAM</dt>
                    <dd class="font-mono tabular text-slate-900">{{ $vehicle->getAttribute('renavam') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Chassi</dt>
                    <dd class="font-mono text-slate-900">{{ $vehicle->getAttribute('chassis') ?: '—' }}</dd>
                </div>
            </dl>
        </div>

        @if ($extraSpecs !== [])
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <h2 class="mb-3 text-sm font-semibold text-slate-900">Especificações adicionais</h2>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    @foreach ($extraSpecs as $item)
                        <div>
                            <dt class="text-2xs uppercase tracking-wide text-slate-400">{{ $item['label'] }}</dt>
                            <dd class="{{ $item['class'] }}">{{ $item['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif

        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Especificações</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Carroceria</dt>
                    <dd class="text-slate-900">{{ $showsBodyAndVolume ? ($vehicle->body_type?->label() ?? '—') : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Combustível</dt>
                    <dd class="text-slate-900">{{ $vehicle->fuel_type?->label() ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Eixos</dt>
                    <dd class="font-mono tabular text-slate-900">{{ $vehicle->getAttribute('axles') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Tanque</dt>
                    <dd class="font-mono tabular text-slate-900">
                        {{ $vehicle->getAttribute('tank_capacity_l') ? $vehicle->getAttribute('tank_capacity_l') . ' L' : '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Tara</dt>
                    <dd class="font-mono tabular text-slate-900">
                        {{ $vehicle->getAttribute('tare_kg') ? $vehicle->getAttribute('tare_kg') . ' kg' : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Capacidade</dt>
                    <dd class="font-mono tabular text-slate-900">
                        {{ collect([
                            $vehicle->getAttribute('capacity_kg') ? $vehicle->getAttribute('capacity_kg') . ' kg' : null,
                            $showsBodyAndVolume && $capacityM3 > 0
                                ? rtrim(rtrim((string) $vehicle->getAttribute('capacity_m3'), '0'), '.') . ' m³'
                                : null,
                        ])->filter()->join(' · ') ?:
                            '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Hodômetro</dt>
                    <dd class="font-mono tabular text-slate-900">
                        {{ Format::km((int) $vehicle->getAttribute('odometer_current')) }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 lg:col-span-2">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Financeiro</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Aquisição</dt>
                    <dd class="text-slate-900">
                        {{ $vehicle->getAttribute('acquisition_date') ? Format::date($vehicle->getAttribute('acquisition_date')) : '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Valor de aquisição</dt>
                    <dd class="font-mono tabular text-slate-900">
                        {{ $vehicle->getAttribute('acquisition_value_cents') !== null ? Format::money((int) $vehicle->getAttribute('acquisition_value_cents')) : '—' }}
                    </dd>
                </div>
            </dl>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 lg:col-span-2">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Propriedade</h2>
            <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Tipo de propriedade</dt>
                    <dd class="text-slate-900">{{ $vehicle->ownership->label() }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Financiado</dt>
                    <dd class="text-slate-900">{{ $isFinanced ? 'Sim' : 'Não' }}</dd>
                </div>
                @if ($isFinanced)
                    @foreach ($propertyDetails as $item)
                        <div>
                            <dt class="text-2xs uppercase tracking-wide text-slate-400">{{ $item['label'] }}</dt>
                            <dd class="{{ $item['class'] }}">{{ $item['value'] }}</dd>
                        </div>
                    @endforeach
                @endif
            </dl>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 lg:col-span-2">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Documentação e vencimentos</h2>
            <dl class="grid gap-3 text-sm sm:grid-cols-3">
                @foreach ($dueFields as $field => $label)
                    @php
                        $dueAt = $vehicle->getAttribute($field);
                        $badge = $badgeForDueField($field);
                    @endphp
                    <div>
                        <dt class="text-2xs uppercase tracking-wide text-slate-400">{{ $label }}</dt>
                        <dd class="mt-0.5 flex items-center gap-1.5">
                            <span class="font-mono tabular text-slate-900">{{ $dueAt ? Format::date($dueAt) : '—' }}</span>
                            @if ($badge)
                                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-2xs font-semibold {{ $badge['classes'] }}">{{ $badge['text'] }}</span>
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>

    <div class="mt-4 rounded-lg border border-slate-200 bg-white p-4">
        <div class="mb-3 flex items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Leituras de hodômetro</h2>
                <p class="text-2xs text-slate-500">Base do km do mês no DRE. Abastecimentos e manutenções já contam; registre aqui quando faltar leitura.</p>
            </div>
        </div>

        @if ($canManage)
            <form method="POST" action="{{ route('vehicles.odometer-readings.store', ['vehicle' => $vehicle->getKey()]) }}"
                class="mb-4 grid gap-2 sm:grid-cols-[10rem_10rem_1fr_auto] sm:items-end">
                @csrf
                <div>
                    <label for="read_on" class="text-2xs font-semibold uppercase tracking-wide text-slate-500">Data</label>
                    <input id="read_on" type="date" name="read_on" value="{{ old('read_on', now()->format('Y-m-d')) }}"
                        class="mt-1 h-9 w-full rounded-md border border-slate-300 bg-white px-2 text-sm text-slate-900" />
                    @error('read_on')<p class="mt-1 text-2xs text-danger-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="odometer" class="text-2xs font-semibold uppercase tracking-wide text-slate-500">Hodômetro (km)</label>
                    <input id="odometer" type="number" min="0" step="1" name="odometer" value="{{ old('odometer') }}"
                        placeholder="{{ (int) $vehicle->getAttribute('odometer_current') }}"
                        class="mt-1 h-9 w-full rounded-md border border-slate-300 bg-white px-2 text-right font-mono tabular text-sm text-slate-900" />
                    @error('odometer')<p class="mt-1 text-2xs text-danger-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="note" class="text-2xs font-semibold uppercase tracking-wide text-slate-500">Observação</label>
                    <input id="note" type="text" name="note" value="{{ old('note') }}" maxlength="255"
                        placeholder="Opcional" class="mt-1 h-9 w-full rounded-md border border-slate-300 bg-white px-2 text-sm text-slate-900" />
                </div>
                <x-ui.button type="submit">Registrar</x-ui.button>
            </form>
        @endif

        @if ($odometerReadings->isEmpty())
            <p class="text-sm text-slate-500">Nenhuma leitura manual registrada.</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-2xs uppercase tracking-wide text-slate-500">
                        <th class="px-2 py-1.5 text-left">Data</th>
                        <th class="px-2 py-1.5 text-right">Hodômetro</th>
                        <th class="px-2 py-1.5 text-left">Observação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($odometerReadings as $reading)
                        <tr class="border-b border-slate-100">
                            <td class="px-2 py-1.5 font-mono tabular text-slate-600">{{ Format::date($reading->getAttribute('read_on')) }}</td>
                            <td class="px-2 py-1.5 text-right font-mono tabular text-slate-900">{{ Format::km((int) $reading->getAttribute('odometer')) }}</td>
                            <td class="px-2 py-1.5 text-slate-600">{{ $reading->getAttribute('note') ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if ($vehicle->getAttribute('notes'))
        <div class="mt-4 rounded-lg border border-slate-200 bg-white p-4">
            <h2 class="mb-2 text-sm font-semibold text-slate-900">Observações</h2>
            <p class="whitespace-pre-line text-sm text-slate-700">{{ $vehicle->getAttribute('notes') }}</p>
        </div>
    @endif
@endsection
