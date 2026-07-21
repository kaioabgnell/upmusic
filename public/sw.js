// Service Worker mínimo — existe só para tornar a PWA instalável (critério do Chrome/Android exige um
// SW registrado com handler de fetch). O POST do Web Share Target vai direto ao backend; o SW não
// precisa interceptá-lo. Sem cache/offline neste MVP (specs/16, Fase 2) — nada além do necessário.

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    event.respondWith(fetch(event.request));
});
