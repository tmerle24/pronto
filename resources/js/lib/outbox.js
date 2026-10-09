import { ref } from 'vue';
import { NetworkError, postJson } from './http';

/**
 * Ordered queue of status changes from the tablet. Persisted, so a reload or a
 * dead network loses nothing; sent strictly in order, server is idempotent per
 * request_id.
 */
export function useOutbox(storageKey, { onResult }) {
    const ops = ref(load());
    const offline = ref(false);
    let sending = false;
    let retryTimer = null;
    let retryDelay = 2000;

    function load() {
        try {
            return JSON.parse(localStorage.getItem(storageKey) ?? '[]');
        } catch {
            return [];
        }
    }

    function persist() {
        try {
            localStorage.setItem(storageKey, JSON.stringify(ops.value));
        } catch {
            // storage full or blocked: queue still works in memory
        }
    }

    function push(op) {
        ops.value.push(op);
        persist();
        flush();
    }

    // drop an op that was not sent yet (undo before the network saw it)
    function cancel(requestId) {
        const index = ops.value.findIndex((op) => op.request_id === requestId && !op.inFlight);
        if (index === -1) return false;
        ops.value.splice(index, 1);
        persist();
        return true;
    }

    async function flush() {
        if (sending) return;
        sending = true;
        clearTimeout(retryTimer);
        try {
            while (ops.value.length) {
                const op = ops.value[0];
                op.inFlight = true;
                const { status, data } = await postJson(op.url, op.body);
                if (status === 419 || status === 401) {
                    // session or csrf token gone: keep the op, reload picks up a fresh token
                    op.inFlight = false;
                    persist();
                    window.location.reload();
                    return;
                }
                ops.value.shift();
                persist();
                offline.value = false;
                retryDelay = 2000;
                onResult(op, status, data);
            }
        } catch (e) {
            if (ops.value[0]) ops.value[0].inFlight = false;
            if (!(e instanceof NetworkError)) throw e;
            offline.value = true;
            retryTimer = setTimeout(flush, retryDelay);
            retryDelay = Math.min(retryDelay * 2, 15000);
        } finally {
            sending = false;
        }
    }

    window.addEventListener('online', flush);
    if (ops.value.length) flush();

    return { ops, offline, push, cancel, flush };
}
