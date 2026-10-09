<script setup>
import MenuIcon from '@/Components/MenuIcon.vue';
import TeamNav from '@/Components/TeamNav.vue';
import Wordmark from '@/Components/Wordmark.vue';
import { useEcho } from '@/lib/echo';
import { euro } from '@/lib/money';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    event: Object,
    items: Array,
    status: Object,
    placed: Object,
});

const VARIANT = { whole: 'ganz', slice: 'Stück' };
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

// slices and drinks usually go straight over the counter; anything whole goes to the kitchen.
// Pre-set from the cart, the team can switch it with one tap.
const needsKitchen = computed(() => lines.value.some((l) => l.item.category !== 'drink' && l.item.variant !== 'slice'));
const kitchenChoice = ref(null);
const toKitchen = computed(() => kitchenChoice.value ?? needsKitchen.value);
watch(() => lines.value.length, (n) => {
    if (n === 0) kitchenChoice.value = null;
});

const form = useForm({ lines: [], payment_method: 'cash', handed_over: false });
function submit(method) {
    form.lines = lines.value.map(({ id, quantity }) => ({ id, quantity }));
    form.payment_method = method;
    form.handed_over = !toKitchen.value;
    form.post(route('team.counter.store'), {
        preserveScroll: true,
        onSuccess: () => {
            clear();
            kitchenChoice.value = null;
        },
    });
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

const groups = computed(() => [
    { title: 'Essen', rows: byProduct(props.items.filter((i) => i.category !== 'drink')) },
    { title: 'Getränke', rows: [props.items.filter((i) => i.category === 'drink')] },
]);

onMounted(() => {
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
            <a :href="route('team.manual')" target="_blank" class="ml-auto grid h-12 place-items-center px-2 text-grigio underline">Hilfe</a>
        </header>

        <p v-if="!event" class="m-auto font-display text-3xl">Kein aktives Event.</p>

        <main v-else class="grid min-h-0 flex-1 gap-3 px-3 pb-3 md:grid-cols-[1fr_380px]">
            <section class="min-h-0 overflow-y-auto">
                <div v-for="group in groups" :key="group.title" class="mb-4">
                    <h2 class="label-caps mb-2 text-grigio">{{ group.title }}</h2>
                    <div v-for="(row, r) in group.rows" :key="r" class="mb-3 grid grid-cols-3 gap-3">
                        <button
                            v-for="item in row"
                            :key="item.id"
                            type="button"
                            class="relative flex min-h-20 items-center gap-3 rounded-card p-3 text-left"
                            :class="[
                                soldOut.has(item.id) ? 'bg-crema-scuro text-grigio' : 'bg-raised',
                                // in the cart: whole tile framed, readable from the corner of the eye
                                quantities[item.id] ? 'ring-3 ring-basilico ring-inset' : '',
                            ]"
                            :disabled="soldOut.has(item.id)"
                            @click="add(item)"
                        >
                            <!-- one row, centred: picture | name over variant | price; gaps equal the tile padding (12 px) -->
                            <span class="flex size-10 shrink-0 items-center justify-center">
                                <MenuIcon :icon="item.icon" :variant="item.variant" class="h-10" />
                            </span>
                            <!-- -2px: Playfair's built-in side bearing plus anti-aliasing, so the visible gap matches -->
                            <span class="-ml-0.5 min-w-0 flex-1 leading-tight">
                                <span class="block font-display text-xl font-bold">
                                    <!-- count as everywhere else in Pronto: "2 × Margherita" -->
                                    <span v-if="quantities[item.id]" class="font-sans font-extrabold text-basilico tabular-nums">{{ quantities[item.id] }}&#8239;×</span>
                                    {{ item.name }}
                                </span>
                                <span v-if="item.variant" class="block text-sm text-grigio">{{ VARIANT[item.variant] }}</span>
                            </span>
                            <span class="shrink-0 font-semibold tabular-nums">{{ soldOut.has(item.id) ? 'Ausverkauft' : euro(item.price_cents + item.deposit_cents) }}</span>
                        </button>
                    </div>
                </div>
            </section>

            <aside class="flex min-h-0 flex-col rounded-card bg-raised p-4">
                <h2 class="label-caps text-grigio">Bestellung</h2>
                <ul class="mt-2 min-h-0 flex-1 divide-y divide-crema-scuro overflow-y-auto">
                    <li v-for="line in lines" :key="line.id" class="flex items-center gap-2 py-2">
                        <span class="flex flex-1 gap-1.5 text-lg">
                            <span class="shrink-0 font-extrabold tabular-nums">{{ line.quantity }}&#8239;×</span>
                            <span class="font-semibold">{{ line.item.name }} <span class="font-normal text-grigio">{{ VARIANT[line.item.variant] ?? '' }}</span></span>
                        </span>
                        <button type="button" class="size-12 rounded-full bg-crema-scuro text-2xl font-bold" :aria-label="`${line.item.name} weniger`" @click="remove(line.id)">-</button>
                    </li>
                    <li v-if="!lines.length" class="py-6 text-center text-grigio">Tippe links auf die Artikel.</li>
                </ul>
                <p class="mt-2 flex justify-between font-display text-3xl font-black">
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
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <button type="button" class="h-[76px] rounded-card bg-basilico text-xl font-extrabold text-white disabled:opacity-50" :disabled="!lines.length || form.processing" @click="submit('cash')">
                        Bar
                    </button>
                    <button type="button" class="h-[76px] rounded-card bg-carbone text-xl leading-tight font-extrabold text-white disabled:opacity-50" :disabled="!lines.length || form.processing" @click="submit('counter_card')">
                        Karte<br /><span class="text-sm font-semibold">(Terminal)</span>
                    </button>
                </div>
            </aside>
        </main>

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
                <p class="text-grigio">{{ receipt.payment_method === 'cash' ? 'Bar bezahlt' : 'Karte am Terminal' }}</p>

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
