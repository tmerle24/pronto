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

const form = useForm({ lines: [], payment_method: 'cash' });
function submit(method) {
    form.lines = lines.value.map(({ id, quantity }) => ({ id, quantity }));
    form.payment_method = method;
    form.post(route('team.counter.store'), { preserveScroll: true, onSuccess: clear });
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
                            <!-- picture | text: gap equals the tile padding (12 px) -->
                            <span class="flex w-full flex-1 items-start gap-3">
                                <MenuIcon :icon="item.icon" :variant="item.variant" class="h-8" />
                                <!-- -2px: Playfair's built-in side bearing plus anti-aliasing, so the visible gap matches -->
                                <span class="-ml-0.5 flex min-h-full flex-1 flex-col justify-between gap-2 self-stretch">
                                    <span class="font-display text-xl leading-tight font-bold">
                                        {{ item.name }} <span class="font-sans text-base font-normal">{{ VARIANT[item.variant] ?? '' }}</span>
                                    </span>
                                    <span class="font-semibold">{{ soldOut.has(item.id) ? 'Ausverkauft' : euro(item.price_cents + item.deposit_cents) }}</span>
                                </span>
                            </span>
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
                        <span class="flex flex-1 gap-1.5 text-lg">
                            <span class="shrink-0 font-extrabold tabular-nums">{{ line.quantity }}×</span>
                            <span class="font-semibold">{{ line.item.name }} <span class="font-normal text-grigio">{{ VARIANT[line.item.variant] ?? '' }}</span></span>
                        </span>
                        <button type="button" class="size-12 rounded-full bg-crema-scuro text-2xl font-bold" :aria-label="`${line.item.name} weniger`" @click="remove(line.id)">-</button>
                    </li>
                    <li v-if="!lines.length" class="py-6 text-center text-grigio">Tippe links auf die Artikel.</li>
                </ul>
                <p class="mt-2 flex justify-between font-display text-3xl font-black">
                    <span>Summe</span><span>{{ euro(total) }}</span>
                </p>
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
                <p class="label-caps text-grigio">Abholnummer</p>
                <p class="font-display text-9xl leading-none font-black text-rosso">{{ receipt.number }}</p>
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
