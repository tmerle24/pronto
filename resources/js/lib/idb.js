// Minimal IndexedDB key-value store; survives reloads and is not cleared as
// eagerly as localStorage. Falls back to localStorage if IndexedDB is missing.
const DB = 'pronto';
const STORE = 'kv';
let dbPromise = null;

function open() {
    dbPromise ??= new Promise((resolve, reject) => {
        const request = indexedDB.open(DB, 1);
        request.onupgradeneeded = () => request.result.createObjectStore(STORE);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
    return dbPromise;
}

function run(mode, fn) {
    return open().then(
        (db) =>
            new Promise((resolve, reject) => {
                const tx = db.transaction(STORE, mode);
                const request = fn(tx.objectStore(STORE));
                tx.oncomplete = () => resolve(request?.result);
                tx.onerror = () => reject(tx.error);
            }),
    );
}

export async function getItem(key) {
    try {
        return await run('readonly', (store) => store.get(key));
    } catch {
        try {
            return JSON.parse(localStorage.getItem(key) ?? 'null');
        } catch {
            return null;
        }
    }
}

export async function setItem(key, value) {
    const plain = JSON.parse(JSON.stringify(value));
    try {
        await run('readwrite', (store) => store.put(plain, key));
    } catch {
        try {
            localStorage.setItem(key, JSON.stringify(plain));
        } catch {
            // nothing left, queue lives in memory
        }
    }
}
