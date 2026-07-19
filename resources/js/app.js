// Máscara de telefone/celular/WhatsApp aplicada a qualquer input com
// data-mask="phone". Formata para (xx) x xxxx-xxxx (celular) ou (xx) xxxx-xxxx
// (fixo) e limita a entrada a 11 dígitos. A base guarda só os dígitos — a
// normalização acontece no servidor (FormRequest).
const PHONE_SELECTOR = '[data-mask="phone"]';

function formatPhone(value) {
    const digits = (value || '').replace(/\D+/g, '').slice(0, 11);

    if (digits.length === 0) {
        return '';
    }

    if (digits.length <= 2) {
        return `(${digits}`;
    }

    const area = digits.slice(0, 2);
    const rest = digits.slice(2);

    if (digits.length <= 6) {
        return `(${area}) ${rest}`;
    }

    if (digits.length <= 10) {
        return `(${area}) ${rest.slice(0, rest.length - 4)}-${rest.slice(rest.length - 4)}`;
    }

    return `(${area}) ${rest.slice(0, 1)} ${rest.slice(1, 5)}-${rest.slice(5)}`;
}

function applyPhoneMask(el) {
    if (el) {
        el.value = formatPhone(el.value);
    }
}

document.addEventListener('input', (event) => {
    const target = event.target;

    if (target instanceof HTMLInputElement && target.matches(PHONE_SELECTOR)) {
        applyPhoneMask(target);
    }
});

function initPhoneMasks() {
    document.querySelectorAll(PHONE_SELECTOR).forEach((el) => {
        if (el.value) {
            applyPhoneMask(el);
        }
    });
}

if (document.readyState !== 'loading') {
    initPhoneMasks();
} else {
    document.addEventListener('DOMContentLoaded', initPhoneMasks);
}

window.frotikaApplyPhoneMask = applyPhoneMask;

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
import './toast';
import './notifications';
import './cte-import';
