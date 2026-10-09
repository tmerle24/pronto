<script setup>
import IntakeControls from '@/Components/IntakeControls.vue';
import MenuIcon from '@/Components/MenuIcon.vue';
import TeamNav from '@/Components/TeamNav.vue';
import Wordmark from '@/Components/Wordmark.vue';
import { registerServiceWorker } from '@/lib/push';
import { ageLabel } from '@/lib/age';
import { connected, useEcho } from '@/lib/echo';
import { getJson, NetworkError } from '@/lib/http';
import { useOutbox } from '@/lib/outbox';
import { chime, unlockAudio } from '@/lib/sound';
import { keepAwake } from '@/lib/wakeLock';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';

const props = defineProps({
    event: Object,
    orders: Array,
    wait: Object,
    status: Object,
    items: Array,
    pause_options: Array,
    server_time: String,
});

const NEXT = { new: 'in_progress', in_progress: 'ready', ready: 'picked_up' };
const COLUMNS = [
    { status: 'new', label: 'Neu', tone: 'bg-carbone text-crema' },
    { status: 'in_progress', label: 'Im Ofen', tone: 'bg-rosso text-white' },
    { status: 'ready', label: 'Pronto', tone: 'bg-basilico text-white' },
];
const STATUS_WORD = { new: 'Im Ofen', in_progress: 'Fertig', ready: 'Abgeholt', picked_up: 'Abgeholt' };

// server truth per order id; picked up cards stay a while so undo can bring them back
const cards = reactive(new Map(props.orders.map((o) => [o.id, o])));
const flashing = reactive(new Set());
const started = ref(false);
const now = ref(Date.now());
const clockOffset = new Date(props.server_time).getTime() - Date.now();
const toast = ref(null);
const wait = ref(props.wait);
const status = ref(props.status);
const notice = ref('');
let toastTimer = null;
let noticeTimer = null;

const outbox = useOutbox(`pronto.outbox.${props.event?.id}`, {
    onResult(op, status, data) {
        if (data.order) cards.set(data.order.id, data.order);
        if (status === 409) showNotice(`Nr. ${data.order?.number}: schon geändert, zeige aktuellen Stand`);
    },
});

// server card plus not yet confirmed local steps
function projected(card) {
    let status = card.status;
    let cashOpen = card.cash_open;
    for (const op of outbox.ops.value) {
        if (op.order_id !== card.id) continue;
        if (op.kind === 'advance' && NEXT[status]) {
            if (status === 'ready') cashOpen = false;
            status = NEXT[status];
        } else if (op.kind === 'undo') {
            status = op.to;
            if (op.reopens_cash) cashOpen = true;
        }
    }
    return { ...card, status, cash_open: cashOpen };
}

const view = computed(() => [...cards.values()].map(projected));
const columns = computed(() =>
    COLUMNS.map((column) => ({
        ...column,
        orders: view.value
            .filter((o) => o.status === column.status)
            .sort((a, b) => (a.queued_at < b.queued_at ? -1 : 1)),
    })),
);

function actionFor(order) {
    if (order.status === 'new') return { label: 'In den Ofen', tone: 'bg-rosso text-white' };
    if (order.status === 'in_progress') return { label: 'Fertig', tone: 'bg-basilico text-white' };
    if (order.cash_open) return { label: 'Bezahlt + Abgeholt', tone: 'bg-oro text-carbone' };
    return { label: 'Abgeholt', tone: 'bg-carbone text-crema' };
}

function advance(order) {
    const to = NEXT[order.status];
    if (!to) return;
    const requestId = crypto.randomUUID();
    outbox.push({
        request_id: requestId,
        order_id: order.id,
        kind: 'advance',
        url: route('team.orders.advance', order.id),
        body: { request_id: requestId },
    });
    showToast({
        request_id: requestId,
        order_id: order.id,
        from: order.status,
        reopens_cash: order.status === 'ready' && order.cash_open,
        text: `Nr. ${order.number}: ${STATUS_WORD[order.status]}`,
    });
}

function undo() {
    const last = toast.value;
    if (!last) return;
    hideToast();
    // not sent yet: just forget it
    if (outbox.cancel(last.request_id)) return;
    const requestId = crypto.randomUUID();
    outbox.push({
        request_id: requestId,
        order_id: last.order_id,
        kind: 'undo',
        to: last.from,
        reopens_cash: last.reopens_cash,
        url: route('team.orders.undo', last.order_id),
        body: { request_id: requestId, undo_of: last.request_id },
    });
}

function showToast(action) {
    clearTimeout(toastTimer);
    toast.value = action;
    toastTimer = setTimeout(hideToast, 5000);
}

function hideToast() {
    clearTimeout(toastTimer);
    toast.value = null;
}

function setStatus(value) {
    status.value = value;
    now.value = Date.now() + clockOffset;
}

function showNotice(text) {
    notice.value = text;
    clearTimeout(noticeTimer);
    noticeTimer = setTimeout(() => (notice.value = ''), 4000);
}

function receive(order) {
    const isNew = !cards.has(order.id) && order.status === 'new';
    cards.set(order.id, order);
    if (isNew) announce(order.id);
}

function announce(id) {
    flashing.add(id);
    setTimeout(() => flashing.delete(id), 2500);
    chime();
}

async function resync() {
    try {
        const state = await getJson(route('team.queue.state'));
        const ids = new Set(state.orders.map((o) => o.id));
        for (const [id, card] of cards) {
            if (!ids.has(id) && card.status !== 'picked_up') cards.delete(id);
        }
        state.orders.forEach(receive);
        wait.value = state.wait;
        status.value = state.status;
    } catch (e) {
        if (!(e instanceof NetworkError)) throw e;
    }
}

function prune() {
    for (const [id, card] of cards) {
        const gone = ['picked_up', 'cancelled'].includes(card.status);
        const pending = outbox.ops.value.some((op) => op.order_id === id);
        if (gone && !pending && toast.value?.order_id !== id) cards.delete(id);
    }
}

async function start() {
    unlockAudio();
    started.value = true;
    await keepAwake();
    document.documentElement.requestFullscreen?.().catch(() => {});
}

let ticker = null;
let resyncTimer = null;
onMounted(() => {
    ticker = setInterval(() => {
        now.value = Date.now() + clockOffset;
        prune();
    }, 15000);
    now.value = Date.now() + clockOffset;

    const echo = useEcho(usePage().props.realtime);
    // auth can fail while the server deploys: retry instead of staying silent
    const subscribeTeam = () => {
        echo?.private(`team.${props.event?.id}`)
            .listen('.order.updated', (e) => receive(e.order))
            .error(() => {
                echo.leave(`team.${props.event?.id}`);
                setTimeout(subscribeTeam, 5000);
            });
    };
    subscribeTeam();
    echo?.channel(`wait.${props.event?.id}`).listen('.wait.updated', (e) => {
        wait.value = e.general;
        setStatus(e.status);
    });
    registerServiceWorker();
    // safety net for missed messages and the socket being down
    resyncTimer = setInterval(resync, 15000);
    window.addEventListener('online', resync);
});

onUnmounted(() => {
    clearInterval(ticker);
    clearInterval(resyncTimer);
});

const live = computed(() => connected.value && !outbox.offline.value);
</script>

<template>
    <Head title="Cucina" />
    <div class="flex h-dvh flex-col bg-crema text-carbone select-none">
        <header class="flex h-16 shrink-0 items-center gap-4 px-4">
            <Wordmark class="text-2xl" />
            <TeamNav current="team.queue" />
            <span class="ml-auto"></span>
            <IntakeControls
                v-if="event"
                :status="status"
                :items="items"
                :pause-options="pause_options"
                :now="now"
                @update:status="setStatus"
                @error="showNotice"
            />
            <span v-if="wait" class="rounded-full bg-raised px-4 py-2 text-lg">
                <span class="label-caps text-grigio">Wartezeit</span>
                <strong class="ml-2">{{ wait.low }}-{{ wait.high }} Min</strong>
            </span>
            <span
                v-if="outbox.offline.value"
                class="rounded-full bg-oro px-4 py-2 font-bold text-carbone"
                role="status"
            >Offline - wird nachgesendet ({{ outbox.ops.value.length }})</span>
            <span v-else class="label-caps flex items-center gap-2" :class="live ? 'text-basilico' : 'text-grigio'">
                <span class="size-3 rounded-full" :class="live ? 'bg-basilico' : 'bg-grigio'"></span>
                {{ live ? 'Live' : 'Verbinde...' }}
            </span>
            <a :href="route('team.manual')" target="_blank" class="grid h-12 place-items-center px-2 text-grigio underline">Hilfe</a>
            <button type="button" class="h-12 px-3 text-grigio underline" @click="router.post(route('team.logout'))">Abmelden</button>
        </header>

        <p v-if="!event" class="m-auto font-display text-3xl">Kein aktives Event.</p>

        <main v-else class="grid min-h-0 flex-1 grid-cols-3 gap-3 px-3 pb-3">
            <section v-for="column in columns" :key="column.status" class="flex min-h-0 flex-col rounded-card bg-crema-scuro">
                <h2 class="flex items-center justify-between rounded-t-card px-4 py-3" :class="column.tone">
                    <span class="label-caps text-base">{{ column.label }}</span>
                    <span class="font-display text-2xl font-black">{{ column.orders.length }}</span>
                </h2>
                <ul class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    <li
                        v-for="order in column.orders"
                        :key="order.id"
                        class="flex overflow-hidden rounded-card bg-raised transition-shadow"
                        :class="{ 'ring-8 ring-oro animate-pulse': flashing.has(order.id) }"
                    >
                        <div class="min-w-0 flex-1 py-3 pr-2 pl-3">
                            <div class="flex items-start justify-between gap-2">
                                <span class="font-display text-6xl leading-none font-black">{{ order.number }}</span>
                                <span v-if="order.cash_open" class="label-caps rounded-full bg-oro px-3 py-1 text-carbone">Bar offen</span>
                            </div>
                            <ul class="mt-2 text-lg leading-snug">
                                <!-- text first; small pictures at the end, one per piece, as a second check of the quantity -->
                                <li v-for="(item, i) in order.items" :key="i" class="flex flex-wrap items-center gap-x-1">
                                    <span class="shrink-0 font-extrabold tabular-nums">{{ item.quantity }}&#8239;×</span>
                                    <span class="font-semibold whitespace-nowrap">
                                        {{ item.name }}
                                        <span v-if="item.variant" class="text-base font-normal text-grigio">{{ item.variant === 'slice' ? 'Stück' : 'ganz' }}</span>
                                    </span>
                                    <span v-if="item.oven && item.icon" class="flex shrink-0 items-center gap-px">
                                        <MenuIcon
                                            v-for="n in Math.min(item.quantity, 6)"
                                            :key="n"
                                            :icon="item.icon"
                                            :variant="item.variant"
                                            class="h-3"
                                        />
                                        <span v-if="item.quantity > 6" class="text-xs font-semibold text-grigio">+{{ item.quantity - 6 }}</span>
                                    </span>
                                </li>
                            </ul>
                            <p class="mt-2 text-sm text-grigio">
                                {{ order.status === 'in_progress' ? 'im Ofen ' : '' }}{{ ageLabel(order.status === 'in_progress' ? order.started_at : order.queued_at, now) }}
                                <span v-if="order.cash_open"> · Bar {{ (order.total_cents / 100).toFixed(2).replace('.', ',') }} €</span>
                                <span v-if="order.status === 'ready' && order.customer_notified_at"> · Gast benachrichtigt</span>
                            </p>
                        </div>
                        <button
                            type="button"
                            class="flex w-38 shrink-0 items-center justify-center px-2 text-center text-xl leading-tight font-extrabold uppercase"
                            :class="actionFor(order).tone"
                            @click="advance(order)"
                        >
                            {{ actionFor(order).label }}
                        </button>
                    </li>
                </ul>
            </section>
        </main>

        <div v-if="toast" class="fixed inset-x-0 bottom-4 z-20 flex justify-center px-4">
            <div class="flex items-center gap-4 rounded-full bg-carbone py-2 pr-2 pl-6 text-lg text-crema shadow-2xl">
                <span>{{ toast.text }}</span>
                <button type="button" class="h-[76px] rounded-full bg-crema px-8 text-xl font-extrabold text-carbone" @click="undo">
                    Rückgängig
                </button>
            </div>
        </div>
        <div v-if="notice" class="fixed inset-x-0 top-20 z-20 mx-auto w-fit rounded-full bg-oro px-6 py-3 font-semibold text-carbone" role="status">{{ notice }}</div>

        <button
            v-if="!started"
            type="button"
            class="fixed inset-0 z-30 flex flex-col items-center justify-center gap-4 bg-carbone/90 text-crema"
            @click="start"
        >
            <span class="font-script text-5xl text-oro">Andiamo!</span>
            <span class="font-display text-4xl font-bold">Tippen zum Starten</span>
            <span class="text-lg">Ton an, Bildschirm bleibt an.</span>
        </button>
    </div>
</template>
