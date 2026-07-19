// Escuta o canal privado do usuário e transforma o evento de conclusão de
// importação de CT-e num toast. É a ponta cliente do ADR-007: o primeiro caso
// de uso real do websocket (Reverb + Echo). O id do usuário vem de uma <meta>
// renderizada pelo layout autenticado.

function currentUserId() {
    const meta = document.querySelector('meta[name="user-id"]');
    const value = meta?.getAttribute('content');

    return value ? Number(value) : null;
}

function importedLabel(count) {
    return count === 1 ? '1 CT-e importado' : `${count} CT-es importados`;
}

function init() {
    const userId = currentUserId();

    if (!userId || !window.Echo) {
        return;
    }

    window.Echo.private(`App.Models.User.${userId}`).listen('.cte-import.completed', (payload) => {
        const imported = Number(payload.imported || 0);
        const failed = Number(payload.failed || 0);

        let message;
        let variant;

        if (failed === 0) {
            message = `${importedLabel(imported)} com sucesso.`;
            variant = 'success';
        } else if (imported === 0) {
            const noun = failed === 1 ? 'arquivo não pôde ser importado' : 'arquivos não puderam ser importados';
            message = `${failed} ${noun}. Veja o detalhe.`;
            variant = 'danger';
        } else {
            message = `${importedLabel(imported)} · ${failed} com erro. Veja o detalhe.`;
            variant = 'warning';
        }

        window.frotikaToast?.({
            title: 'Importação de CT-e concluída',
            message,
            variant,
            href: payload.url,
        });

        // Se o usuário está acompanhando exatamente este lote, atualiza a tela.
        const page = document.getElementById('cte-import-result');

        if (page && page.dataset.uuid === payload.uuid) {
            window.setTimeout(() => window.location.reload(), 400);
        }
    });
}

if (document.readyState !== 'loading') {
    init();
} else {
    document.addEventListener('DOMContentLoaded', init);
}
