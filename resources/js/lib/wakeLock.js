// Keep the screen on; the browser drops the lock when the tab is hidden.
let sentinel = null;
let listening = false;

async function acquire() {
    try {
        sentinel = await navigator.wakeLock.request('screen');
    } catch {
        sentinel = null;
    }
}

export async function keepAwake() {
    if (!('wakeLock' in navigator)) return false;
    await acquire();
    if (!listening) {
        listening = true;
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') acquire();
        });
    }
    return sentinel !== null;
}
