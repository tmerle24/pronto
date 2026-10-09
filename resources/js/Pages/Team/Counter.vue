<script setup>
import TeamNav from '@/Components/TeamNav.vue';
import Wordmark from '@/Components/Wordmark.vue';
import { useEcho } from '@/lib/echo';
import { NetworkError, postJson } from '@/lib/http';
import { euro } from '@/lib/money';
import { cancelCollect, collect, describe, isNativeApp, prepare } from '@/lib/tapToPay';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    event: Object,
    items: Array,
    status: Object,
    placed: Object,
    tapToPay: Object,
});

const VARIANT = { whole: 'ganz', slice: 'Stück' };
const PAID_TEXT = { cash: 'Bar bezahlt', counter_card: 'Karte am Terminal', tap_to_pay: 'Karte bezahlt (Tap to Pay)' };
const quantities = reactive({});
const soldOut = ref(new Set(props.status?.sold_out ?? []));
const byId = Object.fromEntries(props.items.map((i) => [i.id, i]));

const lines = computed(() =>
    Object.entries(quantities)
        .filter(([, q]) => q > 0)
        .map(([id, quantity]) => ({ id: Number(id), quantity, item: byId[id] })),
);
const total = computed(() => lines.value.reduce((sum, l) => sum + (l.item.price_cents + l.item.deposit_cents) * l.quantity, 0));

function add(item) {
    if (soldOut.value.has(item.id)) return;
    quantities[item.id] = Math.min((quantities[item.id] ?? 0) + 1, 20);
}
function remove(id) {
    if (--quantities[id] <= 0) delete quantities[id];
}
function clear() {
    for (const id of Object.keys(quantities)) delete quantities[id];
}

const form = useForm({ lines: [], payment_method: 'cash' });
function submit(method) {
    form.lines = lines.value.map(({ id, quantity }) => ({ id, quantity }));
    form.payment_method = method;
    form.post(route('team.counter.store'), { preserveScroll: true, onSuccess: clear });
}

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
    receipt.value = placed;
    given.value = null;
}

async function startTap() {
    tap.value = { phase: 'starting', total: total.value };
    const { status, data } = await tapRequest(route('team.counter.tap'), { lines: lines.value.map(({ id, quantity }) => ({ id, quantity })) });
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

const groups = computed(() => [
    { title: 'Essen', items: props.items.filter((i) => i.category !== 'drink') },
    { title: 'Getränke', items: props.items.filter((i) => i.category === 'drink') },
]);

onMounted(() => {
    if (tapAvailable) setupTap();
    useEcho(usePage().props.realtime)
        ?.channel(`wait.${props.event?.id}`)
        .listen('.wait.updated', (e) => (soldOut.value = new Set(e.status.sold_out)));
});
</script>

<template>
    <Head title="Cassa" />
    <div class="flex h-dvh flex-col bg-crema text-carbone select-none">
        <header class="flex h-16 shrink-0 items-center gap-4 px-4">
            <Wordmark class="text-2xl" />
            <TeamNav current="team.counter" />
            <span class="truncate text-grigio">{{ event?.name }}</span>
            <Link v-if="!nativeApp" :href="route('team.app')" class="ml-auto grid h-12 place-items-center px-2 text-grigio underline">App</Link>
            <a :href="route('team.manual')" target="_blank" class="grid h-12 place-items-center px-2 text-grigio underline" :class="{ 'ml-auto': nativeApp }">Hilfe</a>
        </header>

        <p v-if="!event" class="m-auto font-display text-3xl">Kein aktives Event.</p>

        <main v-else class="grid min-h-0 flex-1 gap-3 px-3 pb-3 md:grid-cols-[1fr_380px]">
            <section class="min-h-0 overflow-y-auto">
                <div v-for="group in groups" :key="group.title" class="mb-4">
                    <h2 class="label-caps mb-2 text-grigio">{{ group.title }}</h2>
                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
                        <button
                            v-for="item in group.items"
                            :key="item.id"
                            type="button"
                            class="relative flex min-h-24 flex-col items-start justify-between rounded-card p-3 text-left"
                            :class="soldOut.has(item.id) ? 'bg-crema-scuro text-grigio' : 'bg-raised'"
                            :disabled="soldOut.has(item.id)"
                            @click="add(item)"
                        >
                            <span class="font-display text-xl leading-tight font-bold">
                                {{ item.name }} <span class="font-sans text-base font-normal">{{ VARIANT[item.variant] ?? '' }}</span>
                            </span>
                            <span class="font-semibold">{{ soldOut.has(item.id) ? 'Ausverkauft' : euro(item.price_cents + item.deposit_cents) }}</span>
                            <span
                                v-if="quantities[item.id]"
                                class="absolute top-2 right-2 grid size-9 place-items-center rounded-full bg-rosso font-display text-lg font-black text-white"
                            >{{ quantities[item.id] }}</span>
                        </button>
                    </div>
                </div>
            </section>

            <aside class="flex min-h-0 flex-col rounded-card bg-raised p-4">
                <h2 class="label-caps text-grigio">Bestellung</h2>
                <ul class="mt-2 min-h-0 flex-1 divide-y divide-crema-scuro overflow-y-auto">
                    <li v-for="line in lines" :key="line.id" class="flex items-center gap-2 py-2">
                        <span class="flex-1 text-lg">{{ line.quantity }}× {{ line.item.name }} {{ VARIANT[line.item.variant] ?? '' }}</span>
                        <button type="button" class="size-12 rounded-full bg-crema-scuro text-2xl font-bold" :aria-label="`${line.item.name} weniger`" @click="remove(line.id)">-</button>
                    </li>
                    <li v-if="!lines.length" class="py-6 text-center text-grigio">Tippe links auf die Artikel.</li>
                </ul>
                <p class="mt-2 flex justify-between font-display text-3xl font-black">
                    <span>Summe</span><span>{{ euro(total) }}</span>
                </p>
                <p v-if="form.errors.order" class="mt-2 rounded-card bg-rosso p-3 font-semibold text-white" role="alert">{{ form.errors.order }}</p>
                <button
                    v-if="tapAvailable"
                    type="button"
                    class="mt-3 h-[76px] w-full rounded-card bg-carbone text-xl leading-tight font-extrabold text-white disabled:opacity-50"
                    :disabled="!lines.length || form.processing || !!tap"
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
                <p class="label-caps text-grigio">Abholnummer</p>
                <p class="font-display text-9xl leading-none font-black text-rosso">{{ receipt.number }}</p>
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
