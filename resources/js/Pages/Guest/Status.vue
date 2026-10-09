<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import OrderSummary from '@/Components/OrderSummary.vue';
import { t } from '@/lib/i18n';
import { euro } from '@/lib/money';
import { connected, useEcho } from '@/lib/echo';
import { aheadLabel, waitLabel } from '@/lib/wait';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    order: Object,
    event: Object,
    wait: Object,
});

// live copy, replaced by websocket pushes and polling
const live = ref(props.order);
const wait = ref(props.wait);
const order = computed(() => live.value);

const finished = computed(() => ['picked_up', 'cancelled'].includes(order.value.status));
const awaitingPayment = computed(() => !order.value.queued && order.value.status !== 'cancelled');

const statusText = computed(() => ({
    new: t('Bestellung ist angekommen'),
    in_progress: t('Deine Pizza ist im Ofen'),
    ready: t('Pronto! Deine Pizza ist fertig.'),
    picked_up: t('Buon appetito!'),
    cancelled: t('Bestellung storniert'),
})[order.value.status]);

// websocket first; polling every 10 s while it is not connected
let timer = null;
onMounted(() => {
    const echo = useEcho(usePage().props.realtime);
    echo?.channel(`order.${props.order.token}`).listen('.order.updated', (e) => (live.value = e.order));
    echo?.channel(`wait.${props.event.id}`).listen('.wait.updated', (e) => {
        if (order.value.number) wait.value = e.orders[order.value.number] ?? null;
    });
    timer = setInterval(() => {
        if (finished.value || connected.value) return;
        router.reload({
            only: ['order', 'wait'],
            onSuccess: (page) => {
                live.value = page.props.order;
                wait.value = page.props.wait;
            },
        });
    }, 10000);
});
onUnmounted(() => clearInterval(timer));
</script>

<template>
    <Head :title="order.number ? t('Nummer :n', { n: order.number }) : t('Deine Bestellung')" />
    <GuestLayout>
        <template v-if="awaitingPayment">
            <p class="mt-6 font-script text-3xl text-rosso">Un momento</p>
            <h1 class="font-display text-3xl font-bold">{{ t('Wir prüfen deine Zahlung...') }}</h1>
            <p class="mt-2 text-grigio">{{ t('Das dauert meist nur ein paar Sekunden.') }}</p>
            <Link :href="route('guest.order.pay', order.token)" class="mt-4 inline-block py-2 text-carbone underline">{{ t('Zahlung erneut versuchen') }}</Link>
        </template>

        <template v-else-if="order.status === 'cancelled'">
            <h1 class="mt-8 font-display text-3xl font-bold">{{ t('Bestellung storniert') }}</h1>
            <p class="mt-2 text-grigio">{{ t('Komm gern an den Stand, wir helfen dir weiter.') }}</p>
        </template>

        <template v-else>
            <p class="mt-6 font-script text-3xl text-rosso">Grazie!</p>
            <h1 class="font-display text-2xl font-bold">{{ t('Deine Nummer') }}</h1>
            <p class="font-display text-[8rem] leading-none font-black text-rosso" aria-live="polite">{{ order.number }}</p>
            <p class="label-caps mt-2" :class="order.status === 'ready' ? 'text-basilico' : 'text-carbone'">{{ statusText }}</p>

            <div v-if="wait && ['new', 'in_progress'].includes(order.status)" class="mt-4 rounded-card bg-raised p-4" aria-live="polite">
                <p class="font-display text-3xl font-bold">{{ waitLabel(wait) }}</p>
                <p class="text-grigio">{{ aheadLabel(wait.ahead) }}</p>
            </div>

            <p v-if="order.payment_method === 'cash' && order.payment_status === 'pending'" class="mt-4 rounded-card bg-oro p-3 font-semibold text-carbone">
                {{ t('Bitte bezahl :sum bar bei der Abholung.', { sum: euro(order.total_cents) }) }}
            </p>

            <p v-if="!finished" class="mt-6 text-lg">{{ t('Lass diese Seite offen. Wir klingeln, wenn deine Pizza fertig ist.') }}</p>
        </template>

        <h2 class="label-caps mt-8 text-grigio">{{ t('Deine Bestellung') }}</h2>
        <OrderSummary :order="order" class="mt-2" />
    </GuestLayout>
</template>
