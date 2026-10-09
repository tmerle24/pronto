// Load test: guests order in parallel, status pages listen via Reverb, the
// kitchen moves every order through the queue. Measures HTTP latency and
// websocket delivery. Cash orders only, nothing is paid. Against production
// only with a separate test event and LOADTEST_EMAIL/LOADTEST_PASSWORD.
//
//   node scripts/loadtest.mjs [baseUrl] [orders] [listeners]
//   node scripts/loadtest.mjs http://localhost:8047 60 50

import WebSocket from 'ws';

const BASE = process.argv[2] ?? 'http://localhost:8047';
const ORDERS = Number(process.argv[3] ?? 60);
const LISTENERS = Number(process.argv[4] ?? 50);
const CONCURRENCY = 10;

class Client {
    cookies = {};
    async request(path, { method = 'GET', body, json = true } = {}) {
        const headers = {
            Accept: json ? 'application/json' : 'text/html',
            Cookie: Object.entries(this.cookies).map(([k, v]) => `${k}=${v}`).join('; '),
            'X-Requested-With': 'XMLHttpRequest',
        };
        if (this.cookies['XSRF-TOKEN']) headers['X-XSRF-TOKEN'] = decodeURIComponent(this.cookies['XSRF-TOKEN']);
        if (body) headers['Content-Type'] = 'application/json';
        const started = performance.now();
        const response = await fetch(BASE + path, { method, headers, body: body && JSON.stringify(body), redirect: 'manual' });
        for (const c of response.headers.getSetCookie()) {
            const [pair] = c.split(';');
            const i = pair.indexOf('=');
            this.cookies[pair.slice(0, i)] = pair.slice(i + 1);
        }
        return { response, ms: performance.now() - started };
    }
}

function pageProps(html) {
    const match = html.match(/<script[^>]*data-page[^>]*>([\s\S]*?)<\/script>/) ?? html.match(/data-page="([^"]+)"/);
    const raw = match[1].replace(/&quot;/g, '"').replace(/&amp;/g, '&');
    return JSON.parse(raw).props;
}

const stats = (values) => {
    const s = [...values].sort((a, b) => a - b);
    const p = (q) => s[Math.min(s.length - 1, Math.floor(q * s.length))]?.toFixed(0);
    return `n=${s.length} p50=${p(0.5)}ms p95=${p(0.95)}ms max=${s.at(-1)?.toFixed(0)}ms`;
};

async function pool(items, fn) {
    const results = [];
    let next = 0;
    await Promise.all(Array.from({ length: CONCURRENCY }, async () => {
        while (next < items.length) {
            const i = next++;
            results[i] = await fn(items[i], i);
        }
    }));
    return results;
}

// 1. menu and realtime settings
const probe = new Client();
const menu = pageProps(await (await probe.request('/', { json: false })).response.text());
const { event, items } = menu;
const realtime = menu.realtime;
if (!event?.open) throw new Error('Event is not open for orders.');
const orderable = items.filter((i) => !i.sold_out);
console.log(`Event ${event.name}, ${orderable.length} items, realtime ${realtime ? 'on' : 'off'}`);

// kitchen login first, to ignore orders that were already in the queue
const cook = new Client();
await cook.request('/team/login', { json: false });
const login = await cook.request('/team/login', { method: 'POST', body: { email: process.env.LOADTEST_EMAIL ?? 'cucina@pronto.test', password: process.env.LOADTEST_PASSWORD ?? 'pronto-demo' } });
if (![200, 302, 204].includes(login.response.status)) throw new Error('team login failed');
const before = new Set((await (await cook.request('/team/queue/state')).response.json()).orders.map((o) => o.id));

// 2. guests order (cash, no Stripe)
const orderTimes = [];
const tokens = await pool(Array.from({ length: ORDERS }), async () => {
    const guest = new Client();
    await guest.request(`/e/${event.slug}`, { json: false });
    const lines = [{ id: orderable[Math.floor(Math.random() * orderable.length)].id, quantity: 1 + Math.floor(Math.random() * 2) }];
    const { response, ms } = await guest.request(`/e/${event.slug}/orders`, { method: 'POST', body: { lines, payment_method: 'cash' } });
    orderTimes.push(ms);
    const location = response.headers.get('location') ?? '';
    if (response.status !== 302 || !location.includes('/o/')) throw new Error(`order failed: ${response.status}`);
    return location.split('/o/')[1];
});
console.log(`Orders:        ${stats(orderTimes)}`);

// 3. status pages: load + websocket
const statusTimes = [];
const listening = tokens.slice(0, LISTENERS);
await pool(listening, async (token) => {
    const { ms } = await new Client().request(`/o/${token}`, { json: false });
    statusTimes.push(ms);
});
console.log(`Status pages:  ${stats(statusTimes)}`);

const received = new Map();
const sockets = [];
if (realtime) {
    // plain Pusher protocol over ws, so the Origin header can be set like a browser does
    const url = `${realtime.scheme === 'https' ? 'wss' : 'ws'}://${realtime.host}:${realtime.port}/app/${realtime.key}?protocol=7&client=js&version=8.4.0`;
    await Promise.all(listening.map((token) => new Promise((resolve, reject) => {
        const socket = new WebSocket(url, { headers: { Origin: BASE } });
        sockets.push(socket);
        socket.on('error', reject);
        socket.on('message', (raw) => {
            const message = JSON.parse(raw.toString());
            if (message.event === 'pusher:connection_established') {
                socket.send(JSON.stringify({ event: 'pusher:subscribe', data: { channel: `order.${token}` } }));
            } else if (message.event === 'pusher_internal:subscription_succeeded') {
                resolve();
            } else if (message.event === 'order.updated') {
                const order = JSON.parse(message.data).order;
                const list = received.get(token) ?? [];
                list.push({ status: order.status, number: order.number, at: performance.now() });
                received.set(token, list);
            } else if (message.event === 'pusher:ping') {
                socket.send(JSON.stringify({ event: 'pusher:pong', data: {} }));
            }
        });
        setTimeout(() => reject(new Error('websocket subscribe timeout')), 10000);
    })));
    console.log(`Websockets:    ${sockets.length} subscribed`);
}

// 4. kitchen: two cooks advance every order to ready
const state = await (await cook.request('/team/queue/state')).response.json();
state.orders = state.orders.filter((o) => !before.has(o.id));
const ids = state.orders.map((o) => o.id);

const advanceTimes = [];
const sentAt = new Map();
for (const step of ['in_progress', 'ready']) {
    await pool(ids, async (id) => {
        const order = state.orders.find((o) => o.id === id);
        const { response, ms } = await cook.request(`/team/orders/${id}/advance`, { method: 'POST', body: { request_id: crypto.randomUUID() } });
        advanceTimes.push(ms);
        if (response.status !== 200) throw new Error(`advance ${id}: ${response.status}`);
        sentAt.set(`${order.number}:${step}`, performance.now());
    });
}
console.log(`Queue taps:    ${stats(advanceTimes)}`);

// 5. websocket delivery of "ready"
if (realtime) {
    await new Promise((r) => setTimeout(r, 2000));
    const delays = [];
    let missing = 0;
    for (const token of listening) {
        const ready = (received.get(token) ?? []).find((m) => m.status === 'ready');
        if (!ready) missing++;
        else delays.push(ready.at - sentAt.get(`${ready.number}:ready`));
    }
    console.log(`Ready pushes:  ${listening.length - missing}/${listening.length} received${missing ? ` (${missing} missing)` : ''}, delay after tap ${stats(delays)}`);
    sockets.forEach((s) => s.close());
}

console.log('Done. Orders stay in the database (never deleted); reset locally with migrate:fresh --seed.');
process.exit(0);
