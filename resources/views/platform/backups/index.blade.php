@extends('platform.layout')

@section('title', 'Backups | Administração Frotika')

@section('content')
    <x-ui.page-header title="Backups automatizados" subtitle="Rotina de segurança do banco e dos arquivos da aplicação">
        <x-slot:actions>
            <form method="POST" action="{{ route('platform.backups.run-db') }}">
                @csrf
                <x-ui.button type="submit" size="sm">Backup DB</x-ui.button>
            </form>
            <form method="POST" action="{{ route('platform.backups.run-full') }}">
                @csrf
                <x-ui.button type="submit" size="sm" variant="secondary">Backup completo</x-ui.button>
            </form>
            <form method="POST" action="{{ route('platform.backups.clean') }}">
                @csrf
                <x-ui.button type="submit" size="sm" variant="secondary">Limpar antigos</x-ui.button>
            </form>
            <form method="POST" action="{{ route('platform.backups.monitor') }}">
                @csrf
                <x-ui.button type="submit" size="sm" variant="secondary">Monitorar</x-ui.button>
            </form>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <section class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">Último backup</p>
            <p class="mt-1 font-mono text-sm text-slate-900 tabular">{{ $lastBackupLabel ?? '—' }}</p>
        </section>
        <section class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">Arquivos</p>
            <p class="mt-1 font-mono text-lg text-slate-900 tabular text-right">{{ $totalFiles }}</p>
        </section>
        <section class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">Tamanho total</p>
            <p class="mt-1 font-mono text-sm text-slate-900 tabular text-right">{{ $totalSizeLabel }}</p>
        </section>
        <section class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">Último monitoramento</p>
            <p class="mt-1 font-mono text-sm text-slate-900 tabular">{{ $lastMonitorLabel ?? 'Ainda não executado' }}</p>
        </section>
    </div>

    <section class="mb-4 rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">Armazenamento</p>
        <p class="mt-1 text-sm text-slate-700">Disco <span class="font-mono">{{ $backupDisk }}</span> · Diretório <span
                class="font-mono">{{ $backupDirectory }}</span></p>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white">
        <div class="overflow-auto">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-slate-50">
                    <tr class="border-b border-slate-200">
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">
                            Arquivo</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">
                            Data/Hora</th>
                        <th class="px-3 py-2 text-right text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">
                            Tamanho</th>
                        <th class="px-3 py-2 text-right text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">
                            Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($files as $file)
                        <tr class="h-9 border-b border-slate-100">
                            <td class="px-3 py-2 font-mono text-xs text-slate-700">{{ $file['file_name'] }}</td>
                            <td class="px-3 py-2 font-mono text-xs text-slate-600 tabular">
                                {{ $file['last_modified_label'] }}</td>
                            <td class="px-3 py-2 text-right font-mono text-xs text-slate-900 tabular">
                                {{ $file['size_human'] }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('platform.backups.download', ['file' => $file['file_name']]) }}"
                                        class="inline-flex h-8 items-center rounded-md border border-slate-300 px-2.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                        Baixar
                                    </a>
                                    <form method="POST" action="{{ route('platform.backups.destroy') }}"
                                        onsubmit="return confirm('Excluir o backup {{ $file['file_name'] }}? Esta ação não pode ser desfeita.')">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="file" value="{{ $file['file_name'] }}" />
                                        <button type="submit"
                                            class="inline-flex h-8 items-center rounded-md border border-danger-300 px-2.5 text-xs font-medium text-danger-700 hover:bg-danger-50">
                                            Excluir
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-8 text-center text-sm text-slate-500">
                                Nenhum backup encontrado. Execute "Backup DB" para gerar o primeiro dump do banco.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
