@php
    $company = $company ?? null;
    $val = fn (string $field) => old($field, $company?->getAttribute($field));
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    {{-- CNPJ: máscara + consulta na Receita, preenche os campos abaixo. --}}
    <div class="sm:col-span-2">
        <label for="cnpj" class="text-sm font-medium text-slate-700">
            CNPJ <span class="text-danger-700" aria-hidden="true">*</span>
        </label>
        <div class="mt-1.5 flex items-center gap-2">
            <input id="cnpj" name="cnpj" inputmode="numeric" maxlength="18" placeholder="00.000.000/0000-00"
                autocomplete="off" required data-cnpj-url="{{ url('/empresas/cnpj') }}"
                value="{{ $val('cnpj') }}"
                @class([
                    'block h-11 w-full rounded-md border bg-white px-3 text-base text-slate-900 transition-colors placeholder:text-slate-400 focus:ring-2 sm:h-9 sm:text-sm',
                    'border-danger-700 focus:border-danger-700 focus:ring-danger-500/20' => $errors->has('cnpj'),
                    'border-slate-300 focus:border-brand-500 focus:ring-brand-500/20' => ! $errors->has('cnpj'),
                ]) />
            <x-ui.button type="button" id="cnpj-lookup-btn" variant="secondary" class="shrink-0">Buscar</x-ui.button>
        </div>
        <p id="cnpj-status" class="mt-1 text-sm text-slate-500" role="status" aria-live="polite">
            Digite o CNPJ para buscar a empresa na Receita.
        </p>
        @error('cnpj')
            <p class="mt-1 text-sm text-danger-700">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-2">
        <x-ui.input label="Razão social" name="legal_name" :value="$val('legal_name')"
            data-cnpj-field="legal_name" required />
    </div>

    <x-ui.input label="Nome fantasia" name="trade_name" :value="$val('trade_name')"
        data-cnpj-field="trade_name" required />

    <x-ui.select label="Regime tributário" name="tax_regime" required>
        <option value="simples" @selected($val('tax_regime') === 'simples' || $val('tax_regime') === null)>Simples Nacional</option>
        <option value="presumido" @selected($val('tax_regime') === 'presumido')>Lucro Presumido</option>
        <option value="real" @selected($val('tax_regime') === 'real')>Lucro Real</option>
    </x-ui.select>

    <x-ui.input label="Inscrição estadual" name="state_registration" :value="$val('state_registration')"
        placeholder="Opcional" />
    <x-ui.input label="RNTRC" name="rntrc" :value="$val('rntrc')" placeholder="Opcional" />

    {{-- CEP: máscara + consulta no ViaCEP, preenche o endereço abaixo. --}}
    <div class="sm:col-span-2">
        <label for="zip_code" class="text-sm font-medium text-slate-700">CEP</label>
        <div class="mt-1.5 flex items-center gap-2">
            <input id="zip_code" name="zip_code" inputmode="numeric" maxlength="9" placeholder="00000-000"
                autocomplete="off" data-cnpj-field="zip_code" data-cep-url="{{ url('/empresas/cep') }}"
                value="{{ $val('zip_code') }}"
                @class([
                    'block h-11 w-full rounded-md border bg-white px-3 text-base text-slate-900 transition-colors placeholder:text-slate-400 focus:ring-2 sm:h-9 sm:text-sm',
                    'border-danger-700 focus:border-danger-700 focus:ring-danger-500/20' => $errors->has('zip_code'),
                    'border-slate-300 focus:border-brand-500 focus:ring-brand-500/20' => ! $errors->has('zip_code'),
                ]) />
            <x-ui.button type="button" id="cep-lookup-btn" variant="secondary" class="shrink-0">Buscar</x-ui.button>
        </div>
        <p id="cep-status" class="mt-1 text-sm text-slate-500" role="status" aria-live="polite">
            Digite o CEP para buscar o endereço.
        </p>
        @error('zip_code')
            <p class="mt-1 text-sm text-danger-700">{{ $message }}</p>
        @enderror
    </div>

    <input type="hidden" name="ibge_code" data-cep-field="ibge_code" value="{{ $val('ibge_code') }}" />

    <div class="grid grid-cols-[1fr_6rem] gap-3">
        <x-ui.input label="Logradouro" name="street" :value="$val('street')" data-cnpj-field="street"
            data-cep-field="street" />
        <x-ui.input label="Número" name="number" :value="$val('number')" data-cnpj-field="number" />
    </div>

    <x-ui.input label="Complemento" name="complement" :value="$val('complement')" data-cnpj-field="complement" />
    <x-ui.input label="Bairro" name="district" :value="$val('district')" data-cnpj-field="district"
        data-cep-field="district" />

    <div class="grid grid-cols-[1fr_5rem] gap-3">
        <x-ui.input label="Cidade" name="city" :value="$val('city')" data-cnpj-field="city" data-cep-field="city" />
        <x-ui.input label="UF" name="state" :value="$val('state')" data-cnpj-field="state" data-cep-field="state"
            maxlength="2" />
    </div>

    <x-ui.input label="Telefone / WhatsApp" name="phone" :value="$val('phone')" data-cnpj-field="phone"
        data-mask="phone" inputmode="tel" maxlength="16" placeholder="(11) 9 8765-4321" />
    <x-ui.input label="E-mail" name="email" type="email" :value="$val('email')" data-cnpj-field="email"
        placeholder="Opcional" />
</div>

<script>
    (function() {
        const input = document.getElementById('cnpj');
        if (!input) {
            return;
        }

        const button = document.getElementById('cnpj-lookup-btn');
        const status = document.getElementById('cnpj-status');
        const fieldKeys = ['legal_name', 'trade_name', 'zip_code', 'street', 'number', 'complement', 'district',
            'city', 'state', 'phone', 'email'
        ];
        const fields = Object.fromEntries(fieldKeys.map((key) => [key, document.querySelector(
            `[data-cnpj-field="${key}"]`)]));
        const baseUrl = input.dataset.cnpjUrl;
        let lastQueried = null;

        const onlyDigits = (value) => (value || '').replace(/\D+/g, '').slice(0, 14);

        const mask = (digits) => {
            if (digits.length > 12) return `${digits.slice(0,2)}.${digits.slice(2,5)}.${digits.slice(5,8)}/${digits.slice(8,12)}-${digits.slice(12)}`;
            if (digits.length > 8) return `${digits.slice(0,2)}.${digits.slice(2,5)}.${digits.slice(5,8)}/${digits.slice(8)}`;
            if (digits.length > 5) return `${digits.slice(0,2)}.${digits.slice(2,5)}.${digits.slice(5)}`;
            if (digits.length > 2) return `${digits.slice(0,2)}.${digits.slice(2)}`;
            return digits;
        };

        const setStatus = (message, tone) => {
            const tones = {
                info: 'text-slate-500',
                ok: 'text-success-700',
                warn: 'text-warning-700',
                err: 'text-danger-700'
            };
            status.textContent = message;
            status.className = `mt-1 text-sm ${tones[tone] || tones.info}`;
        };

        const fill = (company) => {
            fieldKeys.forEach((key) => {
                const field = fields[key];
                if (field && company[key]) {
                    field.value = company[key];
                }
            });
            // O telefone chega em dígitos crus; dispara o input para a máscara global formatar.
            if (fields.phone && fields.phone.value) {
                fields.phone.dispatchEvent(new Event('input', {
                    bubbles: true
                }));
            }
        };

        const lookup = async () => {
            const digits = onlyDigits(input.value);
            if (digits.length !== 14) {
                setStatus('Digite os 14 dígitos do CNPJ.', 'warn');
                return;
            }
            if (digits === lastQueried) return;
            lastQueried = digits;

            setStatus('Consultando a Receita…', 'info');
            button.disabled = true;

            try {
                const response = await fetch(`${baseUrl}/${digits}`, {
                    headers: {
                        Accept: 'application/json'
                    }
                });
                const body = await response.json().catch(() => ({}));

                if (response.status === 422) {
                    lastQueried = null;
                    setStatus(body.message || 'O CNPJ informado é inválido.', 'err');
                    return;
                }

                if (body.status === 'found') {
                    fill(body.company || {});
                    const place = [body.company?.municipio, body.company?.uf].filter(Boolean).join('/');
                    setStatus(`Empresa encontrada${place ? ' · ' + place : ''}. Confira os dados.`, 'ok');
                    return;
                }

                if (body.status === 'not_found') {
                    setStatus('CNPJ não encontrado na Receita. Preencha os dados manualmente.', 'warn');
                } else {
                    setStatus('Não foi possível consultar agora. Preencha os dados manualmente.', 'warn');
                }
            } catch (error) {
                setStatus('Não foi possível consultar agora. Preencha os dados manualmente.', 'warn');
            } finally {
                button.disabled = false;
            }
        };

        input.addEventListener('input', () => {
            const digits = onlyDigits(input.value);
            input.value = mask(digits);
            if (digits.length !== 14) {
                lastQueried = null;
            }
            if (digits.length === 14) {
                lookup();
            }
        });
        input.addEventListener('blur', () => {
            if (onlyDigits(input.value).length === 14) lookup();
        });
        button.addEventListener('click', lookup);

        input.value = mask(onlyDigits(input.value));
    })();

    (function() {
        const input = document.getElementById('zip_code');
        if (!input || !input.dataset.cepUrl) {
            return;
        }

        const button = document.getElementById('cep-lookup-btn');
        const status = document.getElementById('cep-status');
        const baseUrl = input.dataset.cepUrl;
        const keys = ['street', 'district', 'city', 'state', 'ibge_code'];
        const fields = Object.fromEntries(keys.map((key) => [key, document.querySelector(
            `[data-cep-field="${key}"]`)]));
        const lockedClasses = ['bg-slate-50', 'text-slate-500'];
        let lastQueried = null;

        const onlyDigits = (value) => (value || '').replace(/\D+/g, '').slice(0, 8);
        const mask = (digits) => digits.length > 5 ? `${digits.slice(0,5)}-${digits.slice(5)}` : digits;

        const setStatus = (message, tone) => {
            const tones = {
                info: 'text-slate-500',
                ok: 'text-success-700',
                warn: 'text-warning-700',
                err: 'text-danger-700'
            };
            status.textContent = message;
            status.className = `mt-1 text-sm ${tones[tone] || tones.info}`;
        };

        const setLock = (names, locked) => {
            names.forEach((key) => {
                const field = fields[key];
                if (!field) return;
                field.readOnly = locked;
                lockedClasses.forEach((cls) => field.classList.toggle(cls, locked));
            });
        };

        const unlockAddress = () => setLock(['street', 'district', 'city', 'state'], false);
        const setValue = (key, value) => {
            if (fields[key]) fields[key].value = value || '';
        };

        const lookup = async () => {
            const digits = onlyDigits(input.value);
            if (digits.length !== 8) {
                setStatus('Digite os 8 dígitos do CEP.', 'warn');
                return;
            }
            if (digits === lastQueried) return;
            lastQueried = digits;

            setStatus('Consultando o CEP…', 'info');
            button.disabled = true;

            try {
                const response = await fetch(`${baseUrl}/${digits}`, {
                    headers: {
                        Accept: 'application/json'
                    }
                });
                const body = await response.json().catch(() => ({}));

                if (response.status === 422) {
                    lastQueried = null;
                    unlockAddress();
                    setStatus(body.message || 'O CEP informado é inválido.', 'err');
                    return;
                }

                if (body.status === 'found') {
                    const address = body.address || {};
                    const place = [address.city, address.state].filter(Boolean).join('/');
                    setValue('city', address.city);
                    setValue('state', address.state);
                    setValue('ibge_code', address.ibge_code);
                    setLock(['city', 'state'], true);

                    if (body.generic) {
                        setValue('street', '');
                        setValue('district', '');
                        setLock(['street', 'district'], false);
                        if (fields.street) fields.street.focus();
                        setStatus(`CEP geral de ${place}. Informe logradouro e bairro.`, 'ok');
                    } else {
                        setValue('street', address.street);
                        setValue('district', address.district);
                        setLock(['street', 'district'], true);
                        setStatus(`Endereço encontrado · ${place}. Confira número e complemento.`, 'ok');
                    }
                    return;
                }

                unlockAddress();
                if (body.status === 'not_found') {
                    setStatus('CEP não encontrado. Preencha o endereço manualmente.', 'warn');
                } else {
                    setStatus('Não foi possível consultar o CEP agora. Preencha manualmente.', 'warn');
                }
            } catch (error) {
                unlockAddress();
                setStatus('Não foi possível consultar o CEP agora. Preencha manualmente.', 'warn');
            } finally {
                button.disabled = false;
            }
        };

        input.addEventListener('input', () => {
            const digits = onlyDigits(input.value);
            input.value = mask(digits);
            if (digits.length !== 8) lastQueried = null;
            if (digits.length === 8) lookup();
        });
        input.addEventListener('blur', () => {
            if (onlyDigits(input.value).length === 8) lookup();
        });
        button.addEventListener('click', lookup);

        input.value = mask(onlyDigits(input.value));
    })();
</script>
