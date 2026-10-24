<script setup>
import MenuIcon from '@/Components/MenuIcon.vue';
import TeamNav from '@/Components/TeamNav.vue';
import Wordmark from '@/Components/Wordmark.vue';
import { useEcho } from '@/lib/echo';
import { NetworkError, postJson } from '@/lib/http';
import { euro } from '@/lib/money';
import { cancelCollect, collect, describe, isNativeApp, prepare } from '@/lib/tapToPay';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    event: Object,
    items: Array,
    status: Object,
    placed: Object,
    tapToPay: Object,
});

const VARIANT = { whole: 'ganz', slice: 'Stück', gratinated: 'gratiniert' };
const PAID_TEXT = { cash: 'Bar bezahlt', counter_card: 'Karte am Terminal', tap_to_pay: 'Karte bezahlt (Tap to Pay)' };
const quantities = reactive({});
// individual price per item in cents, an exception set by the team; deposit stays as it is
const customPrices = reactive({});
const soldOut = ref(new Set(props.status?.sold_out ?? []));
const byId = Object.fromEntries(props.items.map((i) => [i.id, i]));

const lines = computed(() =>
    Object.entries(quantities)
        .filter(([, q]) => q > 0)
        .map(([id, quantity]) => ({ id: Number(id), quantity, item: byId[id] })),
);
const unitPrice = (item) => customPrices[item.id] ?? item.price_cents;
const total = computed(() => lines.value.reduce((sum, l) => sum + (unitPrice(l.item) + l.item.deposit_cents) * l.quantity, 0));
const payload = () => lines.value.map(({ id, quantity }) => ({ id, quantity, price_cents: customPrices[id] ?? null }));

function add(item) {
    if (soldOut.value.has(item.id)) return;
    quantities[item.id] = Math.min((quantities[item.id] ?? 0) + 1, 20);
}
function remove(id) {
    if (--quantities[id] <= 0) {
        delete quantities[id];
        delete customPrices[id];
    }
}
function clear() {
    for (const id of Object.keys(quantities)) delete quantities[id];
    for (const id of Object.keys(customPrices)) delete customPrices[id];
}

// price dialog: "6", "6,5", "6,50" or "6.50" euro
const priceEdit = ref(null); // { item, text, error }
function editPrice(item) {
    priceEdit.value = { item, text: (unitPrice(item) / 100).toFixed(2).replace('.', ','), error: null };
    nextTick(() => document.getElementById('custom-price')?.select());
}
function savePrice() {
    const match = priceEdit.value.text.trim().replace(/\s*€$/, '').match(/^(\d{1,3})(?:[.,](\d{1,2}))?$/);
    if (!match) {
        priceEdit.value.error = 'Bitte einen Betrag wie 5,50 eingeben.';
        return;
    }
    const cents = Number(match[1]) * 100 + Number((match[2] ?? '0').padEnd(2, '0'));
    const { item } = priceEdit.value;
    if (cents === item.price_cents) delete customPrices[item.id];
    else customPrices[item.id] = cents;
    priceEdit.value = null;
}
function resetPrice() {
    delete customPrices[priceEdit.value.item.id];
    priceEdit.value = null;
}

// slices and drinks usually go straight over the counter; anything whole goes to the kitchen.
// Pre-set from the cart, the team can switch it with one tap.
const needsKitchen = computed(() => lines.value.some((l) => l.item.category !== 'drink' && l.item.variant !== 'slice'));
const kitchenChoice = ref(null);
const toKitchen = computed(() => kitchenChoice.value ?? needsKitchen.value);
watch(() => lines.value.length, (n) => {
    if (n === 0) kitchenChoice.value = null;
});

const listOpen = ref(false);
const form = useForm({ lines: [], payment_method: 'cash', handed_over: false });
function submit(method) {
    form.lines = payload();
    form.payment_method = method;
    form.handed_over = !toKitchen.value;
    form.post(route('team.counter.store'), {
        preserveScroll: true,
        onHttpException: (response) => (response.status === 419 ? reloadKeepingCart() : undefined),
        onSuccess: () => {
            clear();
            kitchenChoice.value = null;
            listOpen.value = false;
        },
    });
}

// session gone after a long pause (419): nothing was ordered yet. Keep the cart, reload for a
// fresh session (the remember cookie logs in again), the team taps once more.
const SAVED_CART = 'counter-cart';
function reloadKeepingCart() {
    try {
        sessionStorage.setItem(SAVED_CART, JSON.stringify({ quantities, customPrices, kitchenChoice: kitchenChoice.value }));
    } catch {
        // private mode: reload anyway, the cart is gone
    }
    window.location.reload();
    return false;
}
function restoreCart() {
    let saved = null;
    try {
        saved = JSON.parse(sessionStorage.getItem(SAVED_CART) ?? 'null');
        sessionStorage.removeItem(SAVED_CART);
    } catch {
        return;
    }
    if (!saved) return;
    for (const [id, quantity] of Object.entries(saved.quantities ?? {})) if (byId[id]) quantities[id] = quantity;
    for (const [id, cents] of Object.entries(saved.customPrices ?? {})) if (quantities[id]) customPrices[id] = cents;
    kitchenChoice.value = saved.kitchenChoice ?? null;
    form.setError('order', 'Anmeldung erneuert, die Bestellung ist noch da. Bitte nochmal tippen.');
}
restoreCart();

// Tap to Pay: order first (amount from the server), then the native tap screen
const nativeApp = isNativeApp();
const tapAvailable = nativeApp && props.tapToPay?.enabled;
const tapSetupError = ref(null);
const tap = ref(null); // { order, secret, total, phase: starting|waiting|confirming|failed, error }

function setupTap() {
    tapSetupError.value = null;
    prepare(props.tapToPay).catch((e) => (tapSetupError.value = describe(e, 'Keine Verbindung zu Stripe.')));
}

async function tapRequest(url, body = {}) {
    try {
        return await postJson(url, body);
    } catch (e) {
        return { status: 0, data: { message: e instanceof NetworkError ? 'Keine Verbindung. Nochmal versuchen.' : 'Fehler.' } };
    }
}

function placedOrder(placed) {
    tap.value = null;
    clear();
    kitchenChoice.value = null;
    receipt.value = placed;
    given.value = null;
}

async function startTap() {
    tap.value = { phase: 'starting', total: total.value };
    const { status, data } = await tapRequest(route('team.counter.tap'), {
        lines: payload(),
        handed_over: !toKitchen.value,
    });
    if (status === 419 || status === 401) return reloadKeepingCart();
    if (status !== 200) {
        tap.value = null;
        form.setError('order', data.message ?? 'Fehler.');
        return;
    }
    form.clearErrors();
    tap.value = { phase: 'waiting', order: data.order, secret: data.client_secret, total: data.total_cents };
    await runTap();
}

async function runTap() {
    tap.value.phase = 'waiting';
    tap.value.error = null;
    try {
        await collect(props.tapToPay, tap.value.secret);
    } catch (e) {
        if (tap.value) Object.assign(tap.value, { phase: 'failed', error: describe(e) });
        return;
    }
    await confirmTap();
}

async function confirmTap() {
    tap.value.phase = 'confirming';
    const { status, data } = await tapRequest(route('team.counter.tap.complete', tap.value.order));
    if (status === 200) return placedOrder(data.placed);
    Object.assign(tap.value, { phase: 'failed', error: status === 0 ? data.message : 'Zahlung nicht bestätigt.', unconfirmed: status === 0 });
}

async function tapFallback(action) {
    const order = tap.value.order;
    tap.value.phase = 'confirming';
    const { status, data } = await tapRequest(route(`team.counter.tap.${action}`, order));
    if (status !== 200) return Object.assign(tap.value, { phase: 'failed', error: data.message ?? 'Fehler.' });
    if (data.placed) return placedOrder(data.placed);
    tap.value = null; // cancelled, cart stays for a new try
}

// receipt overlay with change helper
const receipt = ref(props.placed);
const given = ref(null);
watch(() => props.placed, (value) => {
    receipt.value = value;
    given.value = null;
});

// food: one row per product, its variants side by side (ganz | Stück), so the same pizza is always in the same place
function byProduct(items) {
    const rows = new Map();
    for (const item of items) {
        if (!rows.has(item.name)) rows.set(item.name, []);
        rows.get(item.name).push(item);
    }
    return [...rows.values()];
}

// tiles (with pictures) or a compact list, less scrolling on a phone; remembered per user on this device,
// so a phone can show the list while the tablet keeps the tiles
const viewKey = `counter-view.${usePage().props.auth?.user?.id ?? 0}`;
const view = ref(readView());
function readView() {
    try {
        return localStorage.getItem(viewKey) === 'list' ? 'list' : 'tiles';
    } catch {
        return 'tiles';
    }
}
watch(view, (value) => {
    try {
        localStorage.setItem(viewKey, value);
    } catch {
        // private mode: only for this visit
    }
});

const groups = computed(() => [
    { title: 'Essen', rows: byProduct(props.items.filter((i) => i.category !== 'drink')) },
    { title: 'Getränke', rows: [props.items.filter((i) => i.category === 'drink')] },
]);

// phone: the pay panel is fixed over the list, its height varies (Tap to Pay, errors, open list),
// so the list gets exactly that much room at the bottom to scroll the last tiles into view
const payPanel = ref(null);
const payHeight = ref(0);
let payObserver = null;
onBeforeUnmount(() => payObserver?.disconnect());

onMounted(() => {
    if (payPanel.value && 'ResizeObserver' in window) {
        payObserver = new ResizeObserver(() => (payHeight.value = payPanel.value?.offsetHeight ?? 0));
        payObserver.observe(payPanel.value);
    }
    if (tapAvailable) setupTap();
    useEcho(usePage().props.realtime)
        ?.channel(`wait.${props.event?.id}`)
        .listen('.wait.updated', (e) => (soldOut.value = new Set(e.status.sold_out)));
});
</script>

<template>
    <Head title="Cassa" />
    <div class="flex h-dvh flex-col bg-crema text-carbone select-none">
        <header class="flex h-16 shrink-0 items-center gap-3 px-3 md:gap-4 md:px-4">
            <Wordmark class="hidden text-2xl sm:inline" />
            <TeamNav current="team.counter" />
            <span class="hidden truncate text-grigio md:inline">{{ event?.name }}</span>
            <Link v-if="!nativeApp" :href="route('team.app')" class="ml-auto grid h-12 place-items-center px-2 text-grigio underline">App</Link>
            <a :href="route('team.manual')" target="_blank" class="grid h-12 place-items-center px-2 text-grigio underline" :class="{ 'ml-auto': nativeApp }">Hilfe</a>
        </header>

        <p v-if="!event" class="m-auto font-display text-3xl">Kein aktives Event.</p>

        <main v-else class="grid min-h-0 flex-1 gap-3 px-3 pb-3 md:grid-cols-[1fr_320px]">
            <!-- phone: room below for the fixed pay panel, measured (fallback 30rem) -->
            <section class="min-h-0 overflow-y-auto pb-(--pay-room) md:pb-0" :style="{ '--pay-room': payHeight ? `${payHeight + 12}px` : '30rem' }">
                <div v-for="(group, g) in groups" :key="group.title" class="mb-4">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <h2 class="label-caps text-grigio">{{ group.title }}</h2>
                        <!-- icons only; no negative margin, the scrolling section would clip it at the top -->
                        <div v-if="g === 0" class="flex shrink-0 rounded-full bg-crema-scuro p-0.5" role="radiogroup" aria-label="Ansicht">
                            <button
                                v-for="option in [{ value: 'tiles', label: 'Kacheln' }, { value: 'list', label: 'Liste' }]"
                                :key="option.value"
                                type="button"
                                role="radio"
                                :aria-checked="view === option.value"
                                :aria-label="option.label"
                                :title="option.label"
                                class="grid size-9 place-items-center rounded-full"
                                :class="view === option.value ? 'bg-carbone text-crema' : 'text-grigio'"
                                @click="view = option.value"
                            >
                                <svg v-if="option.value === 'tiles'" viewBox="0 0 20 20" class="size-5" fill="currentColor" aria-hidden="true">
                                    <rect x="2" y="2" width="7" height="7" rx="1.5" /><rect x="11" y="2" width="7" height="7" rx="1.5" />
                                    <rect x="2" y="11" width="7" height="7" rx="1.5" /><rect x="11" y="11" width="7" height="7" rx="1.5" />
                                </svg>
                                <svg v-else viewBox="0 0 20 20" class="size-5" fill="currentColor" aria-hidden="true">
                                    <rect x="2" y="3" width="16" height="2.5" rx="1.25" /><rect x="2" y="8.75" width="16" height="2.5" rx="1.25" />
                                    <rect x="2" y="14.5" width="16" height="2.5" rx="1.25" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <!-- list: one line per item, about a third of a tile's height -->
                    <ul v-if="view === 'list'" class="divide-y divide-crema-scuro overflow-hidden rounded-card bg-raised md:grid md:grid-cols-2 md:divide-y-0">
                        <li v-for="item in group.rows.flat()" :key="item.id">
                            <button
                                type="button"
                                class="flex min-h-12 w-full items-center gap-2 px-3 py-1.5 text-left"
                                :class="[soldOut.has(item.id) ? 'bg-crema-scuro text-grigio' : '', quantities[item.id] ? 'bg-basilico/10' : '']"
                                :disabled="soldOut.has(item.id)"
                                @click="add(item)"
                            >
                                <span class="flex size-8 shrink-0 items-center justify-center">
                                    <MenuIcon :icon="item.icon" :variant="item.variant" class="h-8" />
                                </span>
                                <span
                                    v-if="quantities[item.id]"
                                    class="shrink-0 rounded-full bg-basilico px-2 text-base leading-6 font-extrabold text-white tabular-nums"
                                >{{ quantities[item.id] }}&#8239;×</span>
                                <span class="min-w-0 flex-1 leading-tight">
                                    <span class="text-lg font-bold">{{ item.name }}</span>
                                    <span v-if="item.variant" class="ml-1.5 text-grigio">{{ VARIANT[item.variant] }}</span>
                                </span>
                                <span v-if="soldOut.has(item.id)" class="shrink-0 text-sm font-semibold">Ausverkauft</span>
                                <span v-else class="shrink-0 text-right font-semibold tabular-nums" :class="{ 'text-rosso': item.id in customPrices }">
                                    {{ euro(unitPrice(item) + item.deposit_cents) }}
                                </span>
                            </button>
                        </li>
                    </ul>
                    <div v-for="(row, r) in view === 'tiles' ? group.rows : []" :key="r" class="mb-2 grid grid-cols-1 gap-2 md:mb-3 md:grid-cols-3 md:gap-3">
                        <button
                            v-for="item in row"
                            :key="item.id"
                            type="button"
                            class="relative flex min-h-18 items-center gap-3 rounded-card p-3 text-left md:min-h-24"
                            :class="[
                                soldOut.has(item.id) ? 'bg-crema-scuro text-grigio' : 'bg-raised',
                                // in the cart: whole tile framed, readable from the corner of the eye
                                quantities[item.id] ? 'ring-3 ring-basilico ring-inset' : '',
                            ]"
                            :disabled="soldOut.has(item.id)"
                            @click="add(item)"
                        >
                            <!-- one row, centred: picture | name over variant | price; gaps equal the tile padding (12 px) -->
                            <span class="flex size-12 shrink-0 items-center justify-center">
                                <MenuIcon :icon="item.icon" :variant="item.variant" class="h-12" />
                            </span>
                            <!-- count as a pill "2 ×" in its own column, so name and variant stay aligned; fine here, the tile has no other buttons -->
                            <span
                                v-if="quantities[item.id]"
                                class="shrink-0 rounded-full bg-basilico px-2 text-base leading-6 font-extrabold text-white tabular-nums"
                            >{{ quantities[item.id] }}&#8239;×</span>
                            <!-- -2px: Playfair's built-in side bearing plus anti-aliasing, so the visible gap matches -->
                            <!-- names may wrap: on a phone a long name must never run into the price -->
                            <span class="min-w-0 flex-1 leading-tight" :class="{ '-ml-0.5': !quantities[item.id] }">
                                <span class="block font-display text-xl font-bold text-balance break-words">{{ item.name }}</span>
                                <span v-if="item.variant" class="block text-sm text-grigio">{{ VARIANT[item.variant] }}</span>
                            </span>
                            <span v-if="soldOut.has(item.id)" class="shrink-0 text-sm font-semibold">Ausverkauft</span>
                            <span v-else class="shrink-0 text-right font-semibold tabular-nums">
                                <span :class="{ 'text-rosso': item.id in customPrices }">{{ euro(unitPrice(item) + item.deposit_cents) }}</span>
                                <span v-if="item.id in customPrices" class="block text-xs font-normal text-grigio line-through">{{ euro(item.price_cents + item.deposit_cents) }}</span>
                            </span>
                        </button>
                    </div>
                </div>
            </section>

            <!-- phone: fixed to the bottom, within thumb reach; tablet: right column -->
            <aside
                ref="payPanel"
                class="fixed inset-x-0 bottom-0 z-20 flex max-h-[85dvh] min-h-0 flex-col rounded-t-[22px] bg-raised p-4 pb-[max(1rem,env(safe-area-inset-bottom))] shadow-[0_-8px_24px_rgba(42,30,25,0.12)] md:static md:max-h-none md:rounded-card md:pb-4 md:shadow-none"
            >
                <h2 class="label-caps hidden text-grigio md:block">Bestellung</h2>
                <!-- phone: list folded away, the pills on the tiles already show the order -->
                <button
                    v-if="lines.length"
                    type="button"
                    class="flex h-10 items-center justify-between font-semibold text-grigio md:hidden"
                    :aria-expanded="listOpen"
                    @click="listOpen = !listOpen"
                >
                    <span>Bestellung ({{ lines.reduce((n, l) => n + l.quantity, 0) }})</span>
                    <span>{{ listOpen ? 'zuklappen' : 'ändern / Preis' }}</span>
                </button>
                <ul class="mt-2 min-h-0 flex-1 divide-y divide-crema-scuro overflow-y-auto md:block" :class="listOpen ? 'block' : 'hidden'">
                    <li v-for="line in lines" :key="line.id" class="flex items-center gap-2 py-2">
                        <span class="flex min-w-0 flex-1 gap-1.5 text-lg">
                            <span class="shrink-0 font-extrabold tabular-nums">{{ line.quantity }}&#8239;×</span>
                            <span class="min-w-0 font-semibold">{{ line.item.name }} <span class="font-normal text-grigio">{{ VARIANT[line.item.variant] ?? '' }}</span></span>
                        </span>
                        <!-- individual price: an exception, so set on purpose in the list, not on the tiles -->
                        <button
                            type="button"
                            class="h-12 shrink-0 rounded-full px-3 font-semibold tabular-nums"
                            :class="line.id in customPrices ? 'bg-rosso text-white' : 'bg-crema-scuro'"
                            :aria-label="`Preis für ${line.item.name} ändern`"
                            @click="editPrice(line.item)"
                        >
                            {{ euro(unitPrice(line.item)) }}
                        </button>
                        <button type="button" class="size-12 rounded-full bg-crema-scuro text-2xl font-bold" :aria-label="`${line.item.name} weniger`" @click="remove(line.id)">-</button>
                    </li>
                    <li v-if="!lines.length" class="py-6 text-center text-grigio">Tippe auf die Artikel.</li>
                </ul>
                <p class="flex justify-between font-display text-3xl font-black md:mt-2">
                    <span>Summe</span><span>{{ euro(total) }}</span>
                </p>
                <div class="mt-3 grid grid-cols-2 rounded-full bg-crema-scuro p-1" role="radiogroup" aria-label="Wohin geht die Bestellung?">
                    <button
                        type="button"
                        role="radio"
                        :aria-checked="!toKitchen"
                        class="h-12 rounded-full font-bold"
                        :class="!toKitchen ? 'bg-carbone text-crema' : 'text-carbone'"
                        @click="kitchenChoice = false"
                    >
                        Direkt mitgeben
                    </button>
                    <button
                        type="button"
                        role="radio"
                        :aria-checked="toKitchen"
                        class="h-12 rounded-full font-bold"
                        :class="toKitchen ? 'bg-rosso text-white' : 'text-carbone'"
                        @click="kitchenChoice = true"
                    >
                        In die Küche
                    </button>
                </div>
                <p v-if="form.errors.order" class="mt-2 rounded-card bg-rosso p-3 font-semibold text-white" role="alert">{{ form.errors.order }}</p>
                <button
                    v-if="tapAvailable"
                    type="button"
                    class="mt-3 h-[76px] w-full rounded-card bg-carbone text-xl leading-tight font-extrabold text-white disabled:opacity-50"
                    :disabled="!lines.length || total < 50 || form.processing || !!tap"
                    @click="startTap"
                >
                    Karte<br /><span class="text-sm font-semibold">(Tap to Pay)</span>
                </button>
                <p v-if="tapSetupError" class="mt-2 text-sm text-rosso">
                    Tap to Pay: {{ tapSetupError }}
                    <button type="button" class="ml-1 underline" @click="setupTap">Erneut verbinden</button>
                </p>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <button type="button" class="h-[76px] rounded-card bg-basilico text-xl font-extrabold text-white disabled:opacity-50" :disabled="!lines.length || form.processing || !!tap" @click="submit('cash')">
                        Bar
                    </button>
                    <button
                        type="button"
                        class="h-[76px] rounded-card text-xl leading-tight font-extrabold disabled:opacity-50"
                        :class="tapAvailable ? 'bg-crema-scuro text-carbone' : 'bg-carbone text-white'"
                        :disabled="!lines.length || form.processing || !!tap"
                        @click="submit('counter_card')"
                    >
                        Karte<br /><span class="text-sm font-semibold">(Terminal)</span>
                    </button>
                </div>
            </aside>
        </main>

        <div v-if="priceEdit" class="fixed inset-0 z-40 flex items-center justify-center bg-carbone/70 p-4" role="dialog" aria-labelledby="custom-price-title">
            <form class="w-full max-w-md rounded-card bg-crema p-6" @submit.prevent="savePrice">
                <p id="custom-price-title" class="font-display text-2xl leading-tight font-bold">
                    {{ priceEdit.item.name }} <span class="font-sans text-lg font-normal text-grigio">{{ VARIANT[priceEdit.item.variant] ?? '' }}</span>
                </p>
                <p class="mt-1 text-grigio">
                    Karte: {{ euro(priceEdit.item.price_cents) }}<template v-if="priceEdit.item.deposit_cents"> (+ {{ euro(priceEdit.item.deposit_cents) }} Pfand bleibt)</template>
                </p>
                <label for="custom-price" class="label-caps mt-4 block text-grigio">Individueller Preis pro Stück</label>
                <div class="mt-2 flex items-center rounded-card bg-raised px-4">
                    <input
                        id="custom-price"
                        v-model="priceEdit.text"
                        type="text"
                        inputmode="decimal"
                        autocomplete="off"
                        class="h-16 min-w-0 flex-1 bg-transparent font-display text-4xl font-black tabular-nums outline-none"
                    />
                    <span class="font-display text-3xl font-bold">€</span>
                </div>
                <p v-if="priceEdit.error" class="mt-2 font-semibold text-rosso" role="alert">{{ priceEdit.error }}</p>
                <button type="submit" class="mt-4 h-[76px] w-full rounded-card bg-carbone text-xl font-extrabold text-white">Preis übernehmen</button>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <button type="button" class="h-14 rounded-card bg-raised text-lg font-bold" @click="resetPrice">Kartenpreis</button>
                    <button type="button" class="h-14 rounded-card bg-raised text-lg font-bold" @click="priceEdit = null">Abbrechen</button>
                </div>
            </form>
        </div>

        <div v-if="tap" class="fixed inset-0 z-40 flex items-center justify-center bg-carbone/70 p-4" role="dialog" aria-live="polite">
            <div class="w-full max-w-md rounded-card bg-crema p-6 text-center">
                <p class="font-display text-5xl font-black">{{ euro(tap.total) }}</p>

                <template v-if="tap.phase === 'waiting'">
                    <p class="mt-4 font-display text-3xl leading-tight font-bold">Karte oder Handy an das Gerät halten</p>
                    <p class="mt-2 text-grigio">Kontaktlos: Karte, Handy oder Uhr</p>
                    <button type="button" class="mt-6 h-14 w-full rounded-card bg-raised text-lg font-bold" @click="cancelCollect">Abbrechen</button>
                </template>

                <p v-else-if="tap.phase === 'starting' || tap.phase === 'confirming'" class="mt-4 font-display text-3xl font-bold">Einen Moment ...</p>

                <template v-else>
                    <p class="mt-4 rounded-card bg-rosso p-3 text-lg font-semibold text-white" role="alert">{{ tap.error }}</p>
                    <div class="mt-4 grid gap-3">
                        <button
                            type="button"
                            class="h-[76px] rounded-card bg-carbone text-xl font-extrabold text-white"
                            @click="tap.unconfirmed ? confirmTap() : runTap()"
                        >
                            {{ tap.unconfirmed ? 'Zahlung erneut prüfen' : 'Nochmal versuchen' }}
                        </button>
                        <button type="button" class="h-[76px] rounded-card bg-basilico text-xl font-extrabold text-white" @click="tapFallback('cash')">
                            Gast zahlt bar
                        </button>
                        <button type="button" class="h-14 rounded-card bg-raised text-lg font-bold" @click="tapFallback('cancel')">Bestellung abbrechen</button>
                    </div>
                </template>
            </div>
        </div>

        <div v-if="receipt" class="fixed inset-0 z-30 flex items-center justify-center bg-carbone/60 p-4">
            <div class="w-full max-w-md rounded-card bg-crema p-6 text-center">
                <template v-if="receipt.handed_over">
                    <p class="font-script text-5xl text-basilico">Grazie!</p>
                    <p class="font-display text-3xl font-bold">Direkt mitgegeben</p>
                </template>
                <template v-else>
                    <p class="label-caps text-grigio">Abholnummer</p>
                    <p class="font-display text-9xl leading-none font-black text-rosso">{{ receipt.number }}</p>
                </template>
                <p class="mt-2 font-display text-3xl font-bold">{{ euro(receipt.total_cents) }}</p>
                <p class="text-grigio">{{ PAID_TEXT[receipt.payment_method] }}</p>

                <template v-if="receipt.payment_method === 'cash'">
                    <p class="label-caps mt-4 text-grigio">Gegeben</p>
                    <div class="mt-2 grid grid-cols-4 gap-2">
                        <button
                            v-for="note in [500, 1000, 2000, 5000]"
                            :key="note"
                            type="button"
                            class="h-14 rounded-card text-lg font-bold disabled:opacity-30"
                            :class="given === note ? 'bg-carbone text-white' : 'bg-raised'"
                            :disabled="note < receipt.total_cents"
                            @click="given = note"
                        >
                            {{ note / 100 }} €
                        </button>
                    </div>
                    <p v-if="given" class="mt-3 font-display text-3xl font-bold">Zurück: {{ euro(given - receipt.total_cents) }}</p>
                </template>

                <button type="button" class="mt-6 h-[76px] w-full rounded-card bg-rosso text-xl font-extrabold text-white" @click="receipt = null">
                    Nächste Bestellung
                </button>
            </div>
        </div>
    </div>
</template>
