// Aprimora o seletor de arquivos da importação em massa de CT-e: lista os XMLs
// escolhidos com nome e tamanho, mostra o contador "N de 20" e trava o envio se
// passar de 20. Progressive enhancement — sem JS o formulário ainda envia; o
// servidor valida o limite de qualquer forma.

const UNITS = ['B', 'KB', 'MB'];
const MAX_FILES = 20;

function humanSize(bytes) {
    let value = bytes;
    let unit = 0;

    while (value >= 1024 && unit < UNITS.length - 1) {
        value /= 1024;
        unit += 1;
    }

    return `${value.toFixed(unit === 0 ? 0 : 1)} ${UNITS[unit]}`;
}

function init() {
    const form = document.querySelector('[data-cte-import]');

    if (!form) {
        return;
    }

    const input = form.querySelector('[data-cte-import-input]');
    const summary = form.querySelector('[data-cte-import-summary]');
    const list = form.querySelector('[data-cte-import-list]');
    const count = form.querySelector('[data-cte-import-count]');
    const warning = form.querySelector('[data-cte-import-warning]');
    const submit = form.querySelector('[data-cte-import-submit]');

    if (!input) {
        return;
    }

    input.addEventListener('change', () => {
        const files = Array.from(input.files || []);

        list.innerHTML = '';
        count.textContent = String(files.length);
        summary.hidden = files.length === 0;

        files.forEach((file) => {
            const item = document.createElement('li');
            item.className = 'flex items-center justify-between gap-3 py-1.5';

            const name = document.createElement('span');
            name.className = 'truncate font-mono text-xs text-slate-700';
            name.textContent = file.name;

            const size = document.createElement('span');
            size.className = 'shrink-0 font-mono text-2xs text-slate-400 tabular';
            size.textContent = humanSize(file.size);

            item.append(name, size);
            list.appendChild(item);
        });

        const tooMany = files.length > MAX_FILES;

        if (warning) {
            warning.hidden = !tooMany;
        }

        if (submit) {
            submit.disabled = tooMany;
        }
    });
}

if (document.readyState !== 'loading') {
    init();
} else {
    document.addEventListener('DOMContentLoaded', init);
}
