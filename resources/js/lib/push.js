// Web push where it works without friction: Android browsers, desktop,
// iOS only as installed PWA (not advertised there).
export function pushSupported() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) return false;
    const ios = /iPad|iPhone|iPod/.test(navigator.userAgent);
    const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    return !ios || standalone;
}

export async function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return null;
    try {
        return await navigator.serviceWorker.register('/sw.js');
    } catch {
        return null;
    }
}

function urlBase64ToUint8Array(base64) {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

export async function subscribePush(vapidPublicKey) {
    const registration = (await registerServiceWorker()) ?? (await navigator.serviceWorker.ready);
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') return null;
    const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
    });
    return subscription.toJSON();
}
