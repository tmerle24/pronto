// JSON requests outside Inertia (queue tablet). Throws NetworkError when offline.
export class NetworkError extends Error {}

export async function postJson(url, body) {
    let response;
    try {
        response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify(body),
        });
    } catch (e) {
        throw new NetworkError(e.message);
    }
    if (response.status >= 500 || response.status === 429) throw new NetworkError(`HTTP ${response.status}`);
    return { status: response.status, data: await response.json().catch(() => ({})) };
}

export async function getJson(url) {
    let response;
    try {
        response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    } catch (e) {
        throw new NetworkError(e.message);
    }
    if (!response.ok) throw new NetworkError(`HTTP ${response.status}`);
    return response.json();
}
