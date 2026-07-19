@extends('layouts.app')

@section('title', 'Importação de CT-e | Frotika')

@php
    use App\Domain\Trips\Enums\CteImportItemStatus;

    $results = $batch->results ?? [];
    $completed = $batch->isCompleted();
@endphp

@section('content')
    <div id="cte-import-result" data-uuid="{{ $batch->uuid }}" data-status="{{ $batch->status->value }}">
        <x-ui.page-header title="Importação de CT-e"
            subtitle="Enviada em {{ Format::dateTime($batch->created_at) }} · {{ $batch->status->label() }}">
            <x-slot:actions>
                <x-ui.link-button href="{{ route('cte.import') }}" variant="secondary" size="sm">Nova importação</x-ui.link-button>
                <x-ui.link-button href="{{ route('cte.index') }}" variant="ghost" size="sm">Ver CT-e</x-ui.link-button>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Faixa de instrumentos: total, importados, falhas, situação. Cor só na falha. --}}
        <section class="rounded-lg border border-slate-200 bg-white">
            <dl class="grid grid-cols-2 divide-slate-200 md:grid-cols-4 md:divide-x">
                <div class="border-b border-slate-200 p-4 md:border-b-0">
                    <dt class="text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">Arquivos</dt>
                    <dd class="mt-1 font-display text-2xl font-bold text-slate-900 tabular">{{ $batch->total_files }}</dd>
                    <p class="mt-1 text-xs text-slate-400">{{ $batch->processed_files }} processados</p>
                </div>
                <div class="border-b border-slate-200 p-4 md:border-b-0">
                    <dt class="text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">Importados</dt>
                    <dd class="mt-1 font-display text-2xl font-bold text-slate-900 tabular">{{ $batch->imported_count }}</dd>
                    <p class="mt-1 text-xs text-slate-400">CT-e cadastrados</p>
                </div>
                <div class="border-b border-slate-200 p-4 md:border-b-0">
                    <dt class="text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">Com erro</dt>
                    <dd @class([
                        'mt-1 font-display text-2xl font-bold tabular',
                        'text-danger-700' => $batch->failed_count > 0,
                        'text-slate-900' => $batch->failed_count === 0,
                    ])>{{ $batch->failed_count }}</dd>
                    <p class="mt-1 text-xs text-slate-400">arquivos não importados</p>
                </div>
                <div class="p-4">
                    <dt class="text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">Situação</dt>
                    <dd class="mt-1 font-display text-lg font-semibold text-slate-900">{{ $batch->status->label() }}</dd>
                    <p class="mt-1 text-xs text-slate-400" data-cte-import-hint>
                        {{ $completed ? 'Processamento concluído' : 'Processando em segundo plano…' }}
                    </p>
                </div>
            </dl>
        </section>

        <section class="mt-6 rounded-lg border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-4 py-2.5">
                <h2 class="font-display text-lg font-semibold text-slate-900">Arquivos do lote</h2>
                <p class="text-xs text-slate-400">Resultado arquivo por arquivo</p>
            </div>

            @if ($results === [])
                <div class="px-4 py-12 text-center">
                    <p class="font-display text-lg font-semibold text-slate-900">Aguardando processamento.</p>
                    <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">
                        Os arquivos foram enviados e entram na fila. O resultado de cada um aparece aqui assim que é
                        processado.
                    </p>
                </div>
            @else
                <div class="overflow-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-slate-50">
                            <tr class="border-b border-slate-200">
                                <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">Arquivo</th>
                                <th class="w-28 px-3 py-2 text-left text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">Situação</th>
                                <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">Detalhe</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($results as $item)
                                @php
                                    $status = CteImportItemStatus::tryFrom($item['status'] ?? '');
                                    $imported = $status === CteImportItemStatus::Imported;
                                @endphp
                                <tr class="h-9 border-b border-slate-100 hover:bg-slate-50">
                                    <td class="px-3">
                                        <span class="font-mono text-xs text-slate-700">{{ $item['file'] ?? '—' }}</span>
                                    </td>
                                    <td class="px-3">
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                            'bg-success-50 text-success-700' => $imported,
                                            'bg-danger-50 text-danger-700' => ! $imported,
                                        ])>{{ $status?->label() ?? 'Desconhecido' }}</span>
                                    </td>
                                    <td class="px-3 py-1.5">
                                        @if ($imported && ! empty($item['cte_id']))
                                            <a href="{{ route('cte.show', ['cte' => $item['cte_id']]) }}"
                                                class="inline-flex items-center gap-2 text-brand-700 hover:underline">
                                                <span class="text-sm">Ver CT-e</span>
                                                @if (! empty($item['access_key']))
                                                    <span class="font-mono text-2xs text-slate-400">{{ $item['access_key'] }}</span>
                                                @endif
                                            </a>
                                        @else
                                            <span class="text-xs text-danger-700">{{ $item['message'] ?? 'Não foi possível importar este arquivo.' }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
