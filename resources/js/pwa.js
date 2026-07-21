/**
 * Registro do Service Worker + banner "Instalar app" (Web Share Target no Android — specs/16, Fase 2).
 * A PWA só resolve o compartilhamento no Android; no iOS isso é feito pelo Atalho (Fase 3), não pela PWA.
 */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

let deferredInstallPrompt = null;

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    if (localStorage.getItem('upmusic_pwa_dismissed') === '1') return;
    document.getElementById('pwa-install-banner')?.classList.remove('hidden');
});

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('pwa-install-btn')?.addEventListener('click', async () => {
        if (!deferredInstallPrompt) return;
        deferredInstallPrompt.prompt();
        await deferredInstallPrompt.userChoice;
        deferredInstallPrompt = null;
        document.getElementById('pwa-install-banner')?.classList.add('hidden');
    });

    document.getElementById('pwa-dismiss-btn')?.addEventListener('click', () => {
        localStorage.setItem('upmusic_pwa_dismissed', '1');
        document.getElementById('pwa-install-banner')?.classList.add('hidden');
    });
});
