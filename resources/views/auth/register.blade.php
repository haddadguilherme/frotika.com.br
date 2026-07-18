@extends('layouts.guest')

@section('title', 'Criar conta | Frotika')

@section('content')
    <div class="mx-auto grid max-w-6xl gap-6 lg:grid-cols-[0.95fr_1.4fr]">
        <section class="rounded-lg border border-brand-800 bg-brand-950 p-6 sm:p-8">
            <p
                class="inline-flex items-center rounded-md bg-accent-500/15 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-accent-300">
                Primeiro acesso
            </p>
            <h1 class="mt-4 font-display text-3xl font-semibold text-white sm:text-4xl">Comece seu DRE Veicular</h1>
            <p class="mt-3 text-sm text-brand-100/95 sm:text-base">
                Cadastre seu usuário e sua empresa para ativar o ambiente inicial do Frotika.
            </p>

            <div class="mt-5 rounded-md border border-brand-700/40 bg-brand-900/50 p-4 text-brand-100">
                <p class="text-sm">
                    Informe o CNPJ e buscamos a razão social e o nome fantasia na Receita para você. O cadastro já cria a
                    conta Caixa, a assinatura trial e o plano de contas base para iniciar rápido.
                </p>
            </div>
        </section>

        <x-ui.card class="border-slate-300 bg-white">
            <h2 class="font-display text-xl font-semibold text-slate-900">Criar conta da transportadora</h2>
            <p class="mt-2 text-sm text-slate-600">Preencha os dados abaixo para liberar o painel.</p>

            <form method="POST" action="{{ route('register.store') }}" class="mt-6 grid gap-4 sm:grid-cols-2">
                @csrf

                <div class="sm:col-span-2">
                    <x-ui.input label="Nome" name="name" placeholder="Nome do responsável" autocomplete="name"
                        required />
                </div>

                <x-ui.input label="E-mail" name="email" type="email" placeholder="voce@empresa.com.br"
                    autocomplete="email" required />

                <x-ui.input label="Senha" name="password" type="password" placeholder="No mínimo 8 caracteres"
                    autocomplete="new-password" required />

                <div class="sm:col-span-2">
                    <x-ui.input label="Nome do grupo" name="group_name" placeholder="Grupo da transportadora" required />
                </div>

                {{-- CNPJ: máscara + consulta automática na Receita. Preenche os campos abaixo. --}}
                <div class="sm:col-span-2">
                    <label for="company_cnpj" class="text-sm font-medium text-slate-700">
                        CNPJ <span class="text-danger-700" aria-hidden="true">*</span>
                    </label>
                    <div class="mt-1.5 flex items-center gap-2">
                        <input id="company_cnpj" name="company_cnpj" inputmode="numeric" maxlength="18"
                            placeholder="00.000.000/0000-00" autocomplete="off" required
                            data-cnpj-url="{{ url('/registrar/cnpj') }}" value="{{ old('company_cnpj') }}"
                            @class([
                                'block h-11 w-full rounded-md border bg-white px-3 text-base text-slate-900 transition-colors placeholder:text-slate-400 focus:ring-2 sm:h-9 sm:text-sm',
                                'border-danger-700 focus:border-danger-700 focus:ring-danger-500/20' => $errors->has(
                                    'company_cnpj'),
                                'border-slate-300 focus:border-brand-500 focus:ring-brand-500/20' => !$errors->has(
                                    'company_cnpj'),
                            ]) />
                        <x-ui.button type="button" id="cnpj-lookup-btn" variant="secondary" class="shrink-0">
                            Buscar
                        </x-ui.button>
                    </div>
                    <p id="cnpj-status" class="mt-1 text-sm text-slate-500" role="status" aria-live="polite">
                        Digite o CNPJ para buscar a empresa na Receita.
                    </p>
                    <button type="button" id="cnpj-manual"
                        class="mt-1 text-sm font-medium text-brand-700 hover:text-brand-800">
                        Preencher manualmente
                    </button>
                    @error('company_cnpj')
                        <p class="mt-1 text-sm text-danger-700">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <x-ui.input label="Razão social" name="company_legal_name"
                        placeholder="Preenchido pela consulta do CNPJ" data-cnpj-field="legal_name" readonly required />
                </div>

                <x-ui.input label="Nome fantasia" name="company_trade_name" placeholder="Preenchido pela consulta do CNPJ"
                    data-cnpj-field="trade_name" readonly required />

                <x-ui.select label="Regime tributário" name="tax_regime" required>
                    <option value="simples" @selected(old('tax_regime', 'simples') === 'simples')>Simples Nacional</option>
                    <option value="presumido" @selected(old('tax_regime') === 'presumido')>Lucro Presumido</option>
                    <option value="real" @selected(old('tax_regime') === 'real')>Lucro Real</option>
                </x-ui.select>

                <input type="hidden" name="company_zip_code" data-cnpj-field="zip_code"
                    value="{{ old('company_zip_code') }}" />
                <input type="hidden" name="company_street" data-cnpj-field="street" value="{{ old('company_street') }}" />
                <input type="hidden" name="company_number" data-cnpj-field="number" value="{{ old('company_number') }}" />
                <input type="hidden" name="company_complement" data-cnpj-field="complement"
                    value="{{ old('company_complement') }}" />
                <input type="hidden" name="company_district" data-cnpj-field="district"
                    value="{{ old('company_district') }}" />
                <input type="hidden" name="company_city" data-cnpj-field="city" value="{{ old('company_city') }}" />
                <input type="hidden" name="company_state" data-cnpj-field="state" value="{{ old('company_state') }}" />
                <input type="hidden" name="company_phone" data-cnpj-field="phone"
                    value="{{ old('company_phone') }}" />
                <input type="hidden" name="company_email" data-cnpj-field="email"
                    value="{{ old('company_email') }}" />

                <div class="mt-2 flex flex-wrap items-center justify-end gap-3 sm:col-span-2">
                    <x-ui.link-button href="{{ route('login') }}" variant="secondary">
                        Já tenho conta
                    </x-ui.link-button>

                    <x-ui.button type="submit">
                        Criar conta
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>

    <script>
        (function() {
            const input = document.getElementById('company_cnpj');
            if (!input) {
                return;
            }

            const button = document.getElementById('cnpj-lookup-btn');
            const status = document.getElementById('cnpj-status');
            const manual = document.getElementById('cnpj-manual');
            const legal = document.querySelector('[data-cnpj-field="legal_name"]');
            const trade = document.querySelector('[data-cnpj-field="trade_name"]');
            const hiddenFieldKeys = ['zip_code', 'street', 'number', 'complement', 'district', 'city', 'state', 'phone',
                'email'
            ];
            const hiddenFields = Object.fromEntries(hiddenFieldKeys.map((key) => [key, document.querySelector(
                `[data-cnpj-field="${key}"]`)]));
            const baseUrl = input.dataset.cnpjUrl;
            const lockedClasses = ['bg-slate-50', 'text-slate-500'];
            let lastQueried = null;

            const onlyDigits = (value) => (value || '').replace(/\D+/g, '').slice(0, 14);

            const mask = (digits) => {
                if (digits.length > 12) {
                    return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8, 12)}-${digits.slice(12)}`;
                }
                if (digits.length > 8) {
                    return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8)}`;
                }
                if (digits.length > 5) {
                    return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5)}`;
                }
                if (digits.length > 2) {
                    return `${digits.slice(0, 2)}.${digits.slice(2)}`;
                }
                return digits;
            };

            const setStatus = (message, tone) => {
                const tones = {
                    info: 'text-slate-500',
                    ok: 'text-success-700',
                    warn: 'text-warning-700',
                    err: 'text-danger-700',
                };
                status.textContent = message;
                status.className = `mt-1 text-sm ${tones[tone] || tones.info}`;
            };

            const clearLookupDetails = () => {
                hiddenFieldKeys.forEach((key) => {
                    const field = hiddenFields[key];
                    if (field) {
                        field.value = '';
                    }
                });
            };

            const applyLookupDetails = (company) => {
                hiddenFieldKeys.forEach((key) => {
                    const field = hiddenFields[key];
                    if (field) {
                        field.value = company[key] || '';
                    }
                });
            };

            const setLocked = (locked) => {
                [legal, trade].forEach((field) => {
                    if (!field) {
                        return;
                    }
                    field.readOnly = locked;
                    lockedClasses.forEach((cls) => field.classList.toggle(cls, locked));
                });
                if (manual) {
                    manual.classList.toggle('hidden', !locked);
                }
            };

            const lookup = async () => {
                const digits = onlyDigits(input.value);
                clearLookupDetails();

                if (digits.length !== 14) {
                    setStatus('Digite os 14 dígitos do CNPJ.', 'warn');
                    return;
                }
                if (digits === lastQueried) {
                    return;
                }
                lastQueried = digits;

                setStatus('Consultando a Receita…', 'info');
                button.disabled = true;

                try {
                    const response = await fetch(`${baseUrl}/${digits}`, {
                        headers: {
                            Accept: 'application/json'
                        },
                    });
                    const body = await response.json().catch(() => ({}));

                    if (response.status === 422) {
                        lastQueried = null;
                        setStatus(body.message || 'O CNPJ informado é inválido.', 'err');
                        return;
                    }

                    if (body.status === 'found') {
                        const company = body.company || {};
                        setLocked(false);
                        if (company.legal_name) {
                            legal.value = company.legal_name;
                        }
                        if (company.trade_name) {
                            trade.value = company.trade_name;
                        }
                        applyLookupDetails(company);
                        const place = [company.municipio, company.uf].filter(Boolean).join('/');
                        const parts = ['Empresa encontrada'];
                        if (place) parts.push(place);
                        if (company.situacao) parts.push(company.situacao);
                        setStatus(`${parts.join(' · ')}. Confira os dados antes de continuar.`, 'ok');
                        return;
                    }

                    setLocked(false);
                    if (body.status === 'not_found') {
                        setStatus(
                            'CNPJ não encontrado na Receita. Preencha a razão social e o nome fantasia manualmente.',
                            'warn');
                    } else {
                        setStatus('Não foi possível consultar agora. Preencha os dados manualmente.', 'warn');
                    }
                } catch (error) {
                    setLocked(false);
                    setStatus('Não foi possível consultar agora. Preencha os dados manualmente.', 'warn');
                } finally {
                    button.disabled = false;
                }
            };

            input.addEventListener('input', () => {
                const digits = onlyDigits(input.value);
                input.value = mask(digits);
                if (digits.length !== 14) {
                    clearLookupDetails();
                    lastQueried = null;
                }
                if (digits.length === 14) {
                    lookup();
                }
            });

            input.addEventListener('blur', () => {
                if (onlyDigits(input.value).length === 14) {
                    lookup();
                }
            });

            button.addEventListener('click', lookup);

            if (manual) {
                manual.addEventListener('click', () => {
                    setLocked(false);
                    setStatus('Preencha a razão social e o nome fantasia.', 'info');
                    if (legal) {
                        legal.focus();
                    }
                });
            }

            input.value = mask(onlyDigits(input.value));

            const hasManualData = (legal && legal.value.trim() !== '') || (trade && trade.value.trim() !== '');
            setLocked(!hasManualData);
        })();
    </script>
@endsection
