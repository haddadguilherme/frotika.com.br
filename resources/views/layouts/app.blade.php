<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    @auth
        <meta name="user-id" content="{{ auth()->id() }}" />
    @endauth
    <title>@yield('title', 'Painel | Frotika')</title>
    <link rel="icon" type="image/png" href="{{ asset('icone-frotika.png') }}" />
    <link rel="apple-touch-icon" href="{{ asset('icone-frotika.png') }}" />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>

<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    @php
        $topbarCompanies = $topbarCompanies ?? collect();
        $topbarCurrentCompanyId = $topbarCurrentCompanyId ?? null;
        $topbarCurrentCompanyName = $topbarCurrentCompanyName ?? 'Empresa ativa';
        $licenseBanner = $licenseBanner ?? null;

        $licenseStatusChip = null;

        if ($licenseBanner !== null) {
            $licenseStatusChip = [
                'label' => $licenseBanner['status_value'] === 'suspended' ? 'Licença suspensa' : 'Licença bloqueada',
                'classes' =>
                    $licenseBanner['status_value'] === 'suspended'
                        ? 'border-danger-300 bg-danger-50 text-danger-700'
                        : 'border-warning-300 bg-warning-50 text-warning-700',
            ];
        }

        $navSections = [
            'Operação' => [
                ['label' => 'Painel', 'route' => 'dashboard', 'active' => 'dashboard'],
                ['label' => 'CT-e', 'route' => 'cte.index', 'active' => 'cte.*'],
                ['label' => 'Abastecimentos', 'route' => 'fuelings.index', 'active' => 'fuelings.*'],
                ['label' => 'Manutenções', 'route' => 'maintenances.index', 'active' => 'maintenances.*'],
            ],
            'Frota' => [
                ['label' => 'Veículos', 'route' => 'vehicles.index', 'active' => 'vehicles.*'],
                ['label' => 'Motoristas', 'route' => 'drivers.index', 'active' => 'drivers.*'],
            ],
            'Financeiro' => [
                ['label' => 'Lançamentos', 'route' => 'financial-entries.index', 'active' => 'financial-entries.*'],
                ['label' => 'Fluxo de caixa', 'route' => 'cash-flow.index', 'active' => 'cash-flow.*'],
                ['label' => 'Contas bancárias', 'route' => 'bank-accounts.index', 'active' => 'bank-accounts.*'],
            ],
            'Análise' => [
                ['label' => 'DRE veicular', 'route' => 'dre.index', 'active' => 'dre.index'],
                ['label' => 'Parâmetros de custo', 'route' => 'cost-parameters.edit', 'active' => 'cost-parameters.*'],
            ],
            'Configurações' => [
                ['label' => 'Empresas', 'route' => 'companies.index', 'active' => 'companies.*'],
                ['label' => 'Parceiros', 'route' => 'partners.index', 'active' => 'partners.*'],
            ],
        ];

        if (($isPlatformAdmin ?? false) === true) {
            $navSections['Plataforma'] = [
                ['label' => 'Painel da plataforma', 'route' => 'platform.groups.index', 'active' => 'platform.*'],
            ];
        }
    @endphp

    <div class="min-h-screen lg:grid lg:grid-cols-[var(--spacing-sidebar)_1fr]">
        <aside
            class="hidden lg:flex lg:flex-col lg:justify-between lg:bg-linear-to-b lg:from-brand-950 lg:to-brand-900 lg:px-3 lg:py-4 lg:text-brand-100">
            <div class="space-y-6">
                <a href="{{ route('dashboard') }}" class="inline-flex rounded-md px-2 py-1.5 hover:bg-brand-800/60">
                    <x-ui.brand on="dark" />
                </a>

                <nav class="space-y-5">
                    @foreach ($navSections as $sectionLabel => $items)
                        <section>
                            <p class="px-2 text-2xs font-semibold uppercase tracking-[0.18em] text-brand-400">
                                {{ $sectionLabel }}</p>
                            <ul class="mt-2 space-y-0.5">
                                @foreach ($items as $item)
                                    <li>
                                        @php
                                            $isActive = isset($item['active']) && request()->routeIs($item['active']);
                                        @endphp
                                        <a href="{{ $item['route'] ? route($item['route']) : '#' }}"
                                            @class([
                                                'block rounded-md px-2 py-1.5 text-sm',
                                                'border-l-[3px] border-accent-500 bg-brand-800 font-medium text-white' => $isActive,
                                                'text-brand-100 hover:bg-brand-800/60' => !$isActive,
                                            ])>
                                            {{ $item['label'] }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endforeach
                </nav>
            </div>

            <div class="border-t border-brand-800/80 pt-3">
                <p class="px-2 text-2xs font-semibold uppercase tracking-[0.14em] text-brand-400">Empresa ativa</p>
                <p class="px-2 text-sm font-medium text-white">{{ $topbarCurrentCompanyName }}</p>
                @if ($licenseStatusChip)
                    <span
                        class="mt-1 inline-flex items-center rounded-full border px-2 py-0.5 text-2xs font-semibold uppercase tracking-widest {{ $licenseStatusChip['classes'] }}">
                        {{ $licenseStatusChip['label'] }}
                    </span>
                @endif

                <form method="POST" action="{{ route('logout') }}" class="mt-2">
                    @csrf
                    <x-ui.button type="submit" variant="ghost" size="sm"
                        class="w-full justify-start text-brand-100 hover:bg-brand-800/60 active:bg-brand-800/80">
                        Sair
                    </x-ui.button>
                </form>
            </div>
        </aside>

        <div class="flex min-h-screen flex-col">
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white safe-t">
                <div class="flex h-(--spacing-topbar) items-center gap-3 px-4 sm:px-6">
                    <a href="{{ route('dashboard') }}"
                        class="inline-flex rounded-md px-2 py-1.5 hover:bg-slate-100 lg:hidden">
                        <x-ui.brand on="light" size="sm" />
                    </a>

                    @if ($topbarCompanies->count() > 1)
                        <form method="POST" action="{{ route('tenancy.switch-company') }}"
                            class="hidden items-center gap-2 lg:flex">
                            @csrf
                            <select name="company_id"
                                class="h-9 min-w-56 rounded-md border border-slate-300 bg-white px-2.5 text-sm text-slate-700 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                                onchange="this.form.requestSubmit()">
                                @foreach ($topbarCompanies as $companyOption)
                                    <option value="{{ $companyOption->getKey() }}" @selected((int) $topbarCurrentCompanyId === $companyOption->getKey())>
                                        {{ $companyOption->getAttribute('trade_name') }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    @endif

                    @if ($licenseStatusChip)
                        <span
                            class="hidden items-center rounded-full border px-2 py-0.5 text-2xs font-semibold uppercase tracking-widest lg:inline-flex {{ $licenseStatusChip['classes'] }}">
                            {{ $licenseStatusChip['label'] }}
                        </span>
                    @endif

                    <label class="relative ml-auto hidden min-w-72 max-w-md flex-1 items-center md:flex">
                        <svg class="pointer-events-none absolute left-3 size-4 text-slate-400" viewBox="0 0 20 20"
                            fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <circle cx="9" cy="9" r="6" />
                            <path d="m14 14 3.5 3.5" stroke-linecap="round" />
                        </svg>
                        <input type="text" placeholder="Placa, motorista, CT-e"
                            class="h-9 w-full rounded-md border border-slate-300 bg-white pl-9 pr-16 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20" />
                        <span
                            class="pointer-events-none absolute right-2 rounded border border-slate-300 px-1.5 py-0.5 font-mono text-2xs text-slate-500 tabular">Ctrl+K</span>
                    </label>

                    <div
                        class="hidden items-center gap-1 xl:flex {{ $topbarCompanies->count() > 1 ? '' : 'ml-auto' }}">
                        <x-ui.link-button href="{{ route('fuelings.create') }}" variant="ghost" size="sm">+
                            Abastecimento</x-ui.link-button>
                        <x-ui.link-button href="{{ route('cte.import') }}" variant="ghost" size="sm">Importar
                            CT-e</x-ui.link-button>
                    </div>

                    <a href="{{ route('welcome') }}"
                        class="inline-flex h-9 items-center rounded-md border border-slate-300 px-3 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Site
                    </a>
                </div>
            </header>

            <main class="flex-1 px-4 pb-24 pt-5 sm:px-6 lg:px-6 lg:pb-6">
                @if ($licenseBanner)
                    <div class="mb-4 rounded-md border border-warning-500/40 bg-warning-50 px-4 py-3">
                        <p class="text-sm font-semibold text-warning-700">
                            Licença do grupo em {{ $licenseBanner['status_label'] }}. Operações de escrita estão
                            bloqueadas.
                        </p>

                        @if ($licenseBanner['amount_cents'] !== null)
                            <p class="mt-1 font-mono text-sm text-slate-900 tabular">
                                <span class="unit">R$</span>
                                {{ Format::moneyDecimal(((int) $licenseBanner['amount_cents']) / 100) }}
                                @if ($licenseBanner['due_date'])
                                    <span class="text-slate-500">· vencimento
                                        {{ Format::date($licenseBanner['due_date']) }}</span>
                                @endif
                            </p>
                        @endif

                        @if ($licenseBanner['boleto_url'] || $licenseBanner['boleto_pdf_url'])
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                                @if ($licenseBanner['boleto_url'])
                                    <a href="{{ $licenseBanner['boleto_url'] }}" target="_blank" rel="noopener"
                                        class="inline-flex items-center rounded-md border border-brand-300 px-2 py-1 font-medium text-brand-700 hover:bg-brand-50">
                                        Abrir boleto
                                    </a>
                                @endif

                                @if ($licenseBanner['boleto_pdf_url'])
                                    <a href="{{ $licenseBanner['boleto_pdf_url'] }}" target="_blank" rel="noopener"
                                        class="inline-flex items-center rounded-md border border-slate-300 px-2 py-1 font-medium text-slate-700 hover:bg-slate-50">
                                        PDF
                                    </a>
                                @endif
                            </div>
                        @endif

                        @if (!($licenseBanner['is_owner'] ?? false) && $licenseBanner['owner_name'])
                            <p class="mt-2 text-xs text-slate-600">
                                Fale com {{ $licenseBanner['owner_name'] }} para regularizar a licença.
                            </p>
                        @endif
                    </div>
                @endif

                @if (session('status'))
                    <div
                        class="mb-4 rounded-md border border-success-500/40 bg-success-50 px-4 py-2.5 text-sm font-medium text-success-700">
                        {{ session('status') }}
                    </div>
                @endif

                @if (session('warning'))
                    <div
                        class="mb-4 rounded-md border border-warning-500/40 bg-warning-50 px-4 py-2.5 text-sm font-medium text-warning-700">
                        {{ session('warning') }}
                    </div>
                @endif

                <div class="mx-auto w-full">
                    @yield('content')
                </div>
            </main>

            <x-ui.developer-credits-footer class="pb-(--spacing-bottomnav) lg:pb-3" />

            <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white lg:hidden safe-b">
                <div class="grid h-(--spacing-bottomnav) grid-cols-5 px-2">
                    <a href="{{ route('dashboard') }}"
                        class="flex flex-col items-center justify-center gap-0.5 text-2xs font-medium text-brand-700">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                            aria-hidden="true">
                            <path d="M3 10.5 12 4l9 6.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"
                                stroke-linejoin="round" />
                        </svg>
                        Início
                    </a>
                    <a href="{{ route('cte.index') }}" @class([
                        'flex flex-col items-center justify-center gap-0.5 text-2xs',
                        'font-medium text-brand-700' => request()->routeIs('cte.*'),
                        'text-slate-400' => !request()->routeIs('cte.*'),
                    ])>
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.5" aria-hidden="true">
                            <path d="M7 3h7l4 4v14H7z" stroke-linejoin="round" />
                            <path d="M14 3v4h4M9.5 12h5M9.5 15.5h5" stroke-linecap="round" />
                        </svg>
                        CT-e
                    </a>
                    <a href="{{ route('financial-entries.create') }}" aria-label="Novo lançamento"
                        class="flex items-center justify-center">
                        <span
                            class="inline-flex size-10 items-center justify-center rounded-md bg-brand-700 text-white active:bg-brand-800">
                            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" aria-hidden="true">
                                <path d="M12 5v14M5 12h14" stroke-linecap="round" />
                            </svg>
                        </span>
                    </a>
                    <a href="#"
                        class="flex flex-col items-center justify-center gap-0.5 text-2xs text-slate-400">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.5" aria-hidden="true">
                            <rect x="4" y="4" width="7" height="7" rx="1" />
                            <rect x="13" y="4" width="7" height="7" rx="1" />
                            <rect x="4" y="13" width="7" height="7" rx="1" />
                            <rect x="13" y="13" width="7" height="7" rx="1" />
                        </svg>
                        Frota
                    </a>
                    <button type="button"
                        class="flex flex-col items-center justify-center gap-0.5 text-2xs text-slate-400">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.5" aria-hidden="true">
                            <circle cx="5" cy="12" r="1.6" />
                            <circle cx="12" cy="12" r="1.6" />
                            <circle cx="19" cy="12" r="1.6" />
                        </svg>
                        Mais
                    </button>
                </div>
            </nav>
        </div>
    </div>
</body>

</html>
