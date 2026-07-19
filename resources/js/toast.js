// Toast do Frotika. É overlay — o único lugar do sistema onde a sombra é
// permitida (shadow-overlay). Cor só como dado: a tarja semântica sinaliza o
// tipo. Empilha no canto inferior direito no desktop e acima da bottom nav no
// mobile. Exposto como window.frotikaToast para uso a partir de qualquer script.

const VARIANTS = {
    success: 'border-success-500/40 bg-success-50',
    warning: 'border-warning-500/40 bg-warning-50',
    danger: 'border-danger-500/40 bg-danger-50',
    info: 'border-info-500/40 bg-info-50',
};

function container() {
    let el = document.getElementById('frotika-toasts');

    if (!el) {
        el = document.createElement('div');
        el.id = 'frotika-toasts';
        el.className =
            'pointer-events-none fixed bottom-20 right-4 z-50 flex w-full max-w-sm flex-col gap-2 sm:bottom-4';
        document.body.appendChild(el);
    }

    return el;
}

export function toast({ title = '', message = '', variant = 'info', href = null, timeout = 9000 } = {}) {
    const wrap = container();

    const card = document.createElement(href ? 'a' : 'div');
    card.className = `pointer-events-auto block rounded-lg border p-3 shadow-overlay ${VARIANTS[variant] || VARIANTS.info}`;

    if (href) {
        card.href = href;
    }

    const strong = document.createElement('p');
    strong.className = 'text-sm font-medium text-slate-900';
    strong.textContent = title;
    card.appendChild(strong);

    if (message) {
        const line = document.createElement('p');
        line.className = 'mt-0.5 text-xs text-slate-600';
        line.textContent = message;
        card.appendChild(line);
    }

    const remove = () => {
        card.remove();

        if (!wrap.children.length) {
            wrap.remove();
        }
    };

    if (!href) {
        card.classList.add('cursor-pointer');
        card.addEventListener('click', remove);
    }

    wrap.appendChild(card);

    if (timeout > 0) {
        window.setTimeout(remove, timeout);
    }

    return card;
}

window.frotikaToast = toast;
