// Service worker: web push for guests, offline shell for team tablets and display.
const CACHE = 'pronto-v1';
// pages that must open even when the market wifi is gone
const OFFLINE_PAGES = [/^\/team\/(queue|counter)$/, /^\/display\/[^/]+$/];

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // hashed build assets never change: cache first
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ??
                    fetch(request).then((response) => {
                        if (response.ok) caches.open(CACHE).then((c) => c.put(request, response.clone()));
                        return response;
                    }),
            ),
        );
        return;
    }

    // team pages: network first, last good copy when offline (state is resynced after)
    const offlinePage = request.mode === 'navigate' && OFFLINE_PAGES.some((re) => re.test(url.pathname));
    if (offlinePage) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok && !response.redirected) caches.open(CACHE).then((c) => c.put(request, response.clone()));
                    return response;
                })
                .catch(() => caches.match(request).then((cached) => cached ?? Response.error())),
        );
    }
});

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = { title: 'Pronto!', body: event.data?.text() ?? '' };
    }
    event.waitUntil(
        self.registration.showNotification(data.title ?? 'Pronto!', {
            body: data.body ?? '',
            tag: data.tag,
            renotify: true,
            requireInteraction: true,
            vibrate: [400, 200, 400, 200, 800],
            icon: '/icons/icon-192.png',
            badge: '/icons/badge-96.png',
            data: { url: data.url ?? '/' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url ?? '/';
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            const open = clients.find((c) => c.url === url);
            return open ? open.focus() : self.clients.openWindow(url);
        }),
    );
});
