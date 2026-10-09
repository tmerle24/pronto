// Service worker: web push for guests. Offline caching follows with the queue tablet work.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

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
