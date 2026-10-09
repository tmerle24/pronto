<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import OrderSummary from '@/Components/OrderSummary.vue';
import { t } from '@/lib/i18n';
import { euro } from '@/lib/money';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    order: Object,
    event: Object,
});

const finished = computed(() => ['picked_up', 'cancelled'].includes(props.order.status));
const awaitingPayment = computed(() => !props.order.queued && props.order.status !== 'cancelled');

const statusText = computed(() => ({
    new: t('Bestellung ist angekommen'),
    in_progress: t('Deine Pizza ist im Ofen'),
    ready: t('Pronto! Deine Pizza ist fertig.'),
    picked_up: t('Buon appetito!'),
    cancelled: t('Bestellung storniert'),
})[props.order.status]);

// fallback until realtime (milestone 3): poll every 10 s
let timer = null;
onMounted(() => {
    timer = setInterval(() => {
        if (!finished.value) router.reload({ only: ['order'] });
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

            <p v-if="order.payment_method === 'cash' && order.payment_status === 'pending'" class="mt-4 rounded-card bg-oro p-3 font-semibold text-carbone">
                {{ t('Bitte bezahl :sum bar bei der Abholung.', { sum: euro(order.total_cents) }) }}
            </p>

            <p v-if="!finished" class="mt-6 text-lg">{{ t('Lass diese Seite offen. Wir klingeln, wenn deine Pizza fertig ist.') }}</p>
        </template>

        <h2 class="label-caps mt-8 text-grigio">{{ t('Deine Bestellung') }}</h2>
        <OrderSummary :order="order" class="mt-2" />
    </GuestLayout>
</template>
