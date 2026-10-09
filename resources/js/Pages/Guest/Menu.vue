<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import MenuIcon from '@/Components/MenuIcon.vue';
import Stepper from '@/Components/Stepper.vue';
import { useCart } from '@/lib/cart';
import { t, variantLabel } from '@/lib/i18n';
import { euro } from '@/lib/money';
import { unlockAudio } from '@/lib/sound';
import { waitLabel } from '@/lib/wait';
import { useEcho } from '@/lib/echo';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    event: Object,
    items: Array,
});

const cart = props.event ? useCart(props.event.slug, props.items) : null;

// live intake state: pause, closed, sold out
const live = ref({ open: props.event?.open, paused_minutes: props.event?.paused_minutes, wait: props.event?.wait });
const soldOut = ref(new Set(props.items.filter((i) => i.sold_out).map((i) => i.id)));
onMounted(() => {
    if (!props.event) return;
    useEcho(usePage().props.realtime)
        ?.channel(`wait.${props.event.id}`)
        .listen('.wait.updated', (e) => {
            live.value = { open: e.status.open, paused_minutes: e.status.paused_minutes, wait: e.general };
            soldOut.value = new Set(e.status.sold_out);
            for (const id of soldOut.value) delete cart.quantities[id];
        });
});
const edition = ref(props.event?.editions[0] ?? 'pizza');
const showEditionToggle = computed(() => (props.event?.editions.length ?? 0) > 1);

const groups = computed(() => {
    const visible = props.items.filter((i) => i.edition === null || i.edition === edition.value);
    const food = visible.filter((i) => i.category !== 'drink');
    const drinks = visible.filter((i) => i.category === 'drink');
    return [
        { key: 'food', title: edition.value === 'flammkuchen' ? t('Flammkuchen') : t('Pizza'), items: food },
        { key: 'drinks', title: t('Getränke'), items: drinks },
    ].filter((g) => g.items.length);
});

const sheetOpen = ref(false);
const form = useForm({ lines: [], payment_method: 'online' });

function openCheckout() {
    unlockAudio();
    sheetOpen.value = true;
}

function submit(method) {
    unlockAudio();
    form.lines = cart.lines.value;
    form.payment_method = method;
    form.post(route('guest.checkout', props.event.slug), {
        preserveScroll: 'errors',
        onSuccess: () => cart.clear(),
    });
}
</script>

<template>
    <Head :title="t('Il menù')" />
    <GuestLayout>
        <template v-if="event">
            <p class="mt-6 font-script text-3xl text-rosso">Il menù</p>
            <h1 class="font-display text-4xl font-bold">{{ t("Was darf's sein?") }}</h1>
            <p class="mt-1 text-grigio">
                {{ event.name }}<template v-if="live.open && live.wait"> · {{ t('Wartezeit') }} {{ waitLabel(live.wait) }}</template>
            </p>

            <div v-if="!live.open" class="mt-6 rounded-card bg-carbone p-4 text-crema" role="status">
                <p class="font-display text-xl font-bold">
                    {{ live.paused_minutes ? t('Kurze Pause, der Ofen ist voll.') : t('Gerade nehmen wir keine Bestellungen an.') }}
                </p>
                <p v-if="live.paused_minutes" class="mt-1">{{ t('Bestellen geht wieder in ca. :min Min.', { min: live.paused_minutes }) }}</p>
                <p v-if="event.pause_message" class="mt-1">{{ event.pause_message }}</p>
            </div>

            <div v-if="showEditionToggle" class="mt-6 flex rounded-full bg-crema-scuro p-1" role="tablist">
                <button
                    v-for="e in event.editions"
                    :key="e"
                    type="button"
                    role="tab"
                    :aria-selected="edition === e"
                    class="h-11 flex-1 rounded-full font-semibold"
                    :class="edition === e ? 'bg-rosso text-white' : 'text-carbone'"
                    @click="edition = e"
                >
                    {{ e === 'pizza' ? t('Pizza') : t('Flammkuchen') }}
                </button>
            </div>

            <section v-for="group in groups" :key="group.key" class="mt-6">
                <h2 class="label-caps text-grigio">{{ group.title }}</h2>
                <ul class="mt-2 space-y-3">
                    <li
                        v-for="item in group.items"
                        :key="item.id"
                        class="flex items-center gap-3 rounded-card bg-raised p-4"
                        :class="{ 'opacity-60': soldOut.has(item.id) }"
                    >
                        <!-- picture | text: gap equals the card padding (16 px) -->
                        <div class="flex min-w-0 flex-1 items-start gap-4">
                            <MenuIcon :icon="item.icon" :variant="item.variant" class="h-9" />
                            <!-- -1px: Playfair's built-in side bearing, so the visible gap matches -->
                            <div class="-ml-px min-w-0 flex-1">
                                <p class="font-display text-xl leading-tight font-bold">
                                    {{ item.name }}
                                    <span v-if="item.variant" class="font-sans text-base font-normal text-grigio">{{ variantLabel(item.variant) }}</span>
                                </p>
                                <p class="text-sm text-grigio">{{ item.description }}</p>
                                <p class="mt-1 font-display text-lg font-bold">
                                    {{ euro(item.price_cents) }}
                                    <span v-if="item.deposit_cents" class="font-sans text-sm font-normal text-grigio">+ {{ euro(item.deposit_cents) }} {{ t('Pfand') }}</span>
                                </p>
                                <p v-if="soldOut.has(item.id)" class="label-caps mt-1 text-rosso">{{ t('Ausverkauft') }}</p>
                                <p v-else-if="item.allergens || item.additives" class="mt-1 text-xs text-grigio">
                                    {{ t('Allergene und Zusatzstoffe') }}: {{ [item.allergens, item.additives].filter(Boolean).join(' · ') }}
                                </p>
                            </div>
                        </div>
                        <Stepper
                            :name="[item.name, variantLabel(item.variant)].filter(Boolean).join(' ')"
                            :quantity="cart.quantities[item.id] ?? 0"
                            :disabled="soldOut.has(item.id) || !live.open"
                            @add="cart.add(item.id)"
                            @remove="cart.remove(item.id)"
                        />
                    </li>
                </ul>
            </section>

            <div v-if="cart.count.value > 0 && live.open" class="fixed inset-x-0 bottom-0 z-10 bg-crema/95 px-4 pt-3 pb-[max(1rem,env(safe-area-inset-bottom))] backdrop-blur">
                <button
                    type="button"
                    class="mx-auto block h-14 w-full max-w-xl rounded-full bg-rosso text-lg font-extrabold text-white"
                    @click="openCheckout"
                >
                    {{ t('Bezahlen') }} · {{ euro(cart.totalCents.value) }}
                </button>
            </div>

            <div v-if="sheetOpen" class="fixed inset-0 z-20 flex items-end bg-carbone/50" @click.self="sheetOpen = false">
                <div class="mx-auto w-full max-w-xl rounded-t-[22px] bg-crema px-4 pt-5 pb-[max(1.5rem,env(safe-area-inset-bottom))]" role="dialog" aria-modal="true">
                    <div class="flex items-baseline justify-between">
                        <h2 class="font-display text-2xl font-bold">{{ t('Wie zahlst du?') }}</h2>
                        <button type="button" class="h-11 px-2 text-grigio underline" @click="sheetOpen = false">{{ t('Zurück') }}</button>
                    </div>
                    <p class="mt-1 text-grigio">{{ t('Summe') }}: <strong class="text-carbone">{{ euro(cart.totalCents.value) }}</strong></p>

                    <p v-if="form.errors.order" class="mt-3 rounded-card bg-rosso p-3 font-semibold text-white" role="alert">{{ form.errors.order }}</p>

                    <button
                        v-if="event.online_allowed"
                        type="button"
                        class="mt-4 h-14 w-full rounded-full bg-carbone text-lg font-extrabold text-white disabled:opacity-60"
                        :disabled="form.processing"
                        @click="submit('online')"
                    >
                        {{ t('Online bezahlen') }}
                    </button>
                    <p v-if="event.online_allowed" class="mt-1 text-center text-sm text-grigio">Apple Pay · Google Pay · {{ t('Karte') }} · PayPal</p>
                    <button
                        v-if="event.cash_allowed"
                        type="button"
                        class="mt-3 h-14 w-full rounded-full border-2 border-carbone text-lg font-bold text-carbone disabled:opacity-60"
                        :disabled="form.processing"
                        @click="submit('cash')"
                    >
                        {{ t('Bar bei Abholung') }}
                    </button>
                </div>
            </div>
        </template>
        <p v-else class="mt-10 font-display text-2xl">{{ t('Gerade ist kein Markt geöffnet. Bis bald!') }}</p>
    </GuestLayout>
</template>
