<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import OrderSummary from '@/Components/OrderSummary.vue';
import { t } from '@/lib/i18n';
import { euro } from '@/lib/money';
import { unlockAudio } from '@/lib/sound';
import { Head, Link, router } from '@inertiajs/vue3';
import { loadStripe } from '@stripe/stripe-js';
import { onMounted, ref } from 'vue';

const props = defineProps({
    order: Object,
    event: Object,
    stripe: Object,
    return_url: String,
});

const ready = ref(false);
const paying = ref(false);
const error = ref('');
let stripe = null;
let elements = null;

const css = getComputedStyle(document.documentElement);
const token = (name) => css.getPropertyValue(`--${name}`).trim();

onMounted(async () => {
    if (!props.stripe) return;
    stripe = await loadStripe(props.stripe.key);
    elements = stripe.elements({
        clientSecret: props.stripe.client_secret,
        locale: document.documentElement.lang || 'de',
        appearance: {
            theme: 'flat',
            variables: {
                colorPrimary: token('rosso'),
                colorBackground: token('raised'),
                colorText: token('carbone'),
                colorTextSecondary: token('grigio'),
                borderRadius: '14px',
                fontFamily: 'Inter, system-ui, sans-serif',
            },
        },
    });

    // Apple Pay / Google Pay first, card and PayPal below
    const express = elements.create('expressCheckout', { buttonHeight: 52 });
    express.on('confirm', () => confirm());
    express.mount('#express-checkout');

    elements.create('payment', { layout: 'tabs', wallets: { applePay: 'never', googlePay: 'never' } }).mount('#payment-element');
    ready.value = true;
});

async function confirm() {
    unlockAudio();
    paying.value = true;
    error.value = '';
    const { error: failure } = await stripe.confirmPayment({
        elements,
        confirmParams: { return_url: props.return_url },
    });
    // only reached on failure, success redirects to the status page
    error.value = failure?.message ?? t('Die Zahlung hat nicht geklappt. Versuch es nochmal.');
    paying.value = false;
}

function simulate() {
    unlockAudio();
    router.post(route('guest.order.simulate', props.order.token));
}
</script>

<template>
    <Head :title="t('Bezahlen')" />
    <GuestLayout>
        <p class="mt-6 font-script text-3xl text-rosso">Quasi pronto!</p>
        <h1 class="font-display text-4xl font-bold">{{ t('Bezahlen') }} · {{ euro(order.total_cents) }}</h1>

        <OrderSummary :order="order" class="mt-6" />

        <template v-if="stripe">
            <div id="express-checkout" class="mt-6"></div>
            <div id="payment-element" class="mt-4"></div>
            <p v-if="error" class="mt-3 rounded-card bg-rosso p-3 font-semibold text-white" role="alert">{{ error }}</p>
            <button
                type="button"
                class="mt-4 h-14 w-full rounded-full bg-rosso text-lg font-extrabold text-white disabled:opacity-60"
                :disabled="!ready || paying"
                @click="confirm"
            >
                {{ paying ? t('Einen Moment...') : `${t('Jetzt bezahlen')} · ${euro(order.total_cents)}` }}
            </button>
        </template>
        <div v-else class="mt-6 rounded-card border-2 border-dashed border-grigio p-4">
            <p class="label-caps text-grigio">{{ t('Demo ohne Stripe') }}</p>
            <button type="button" class="mt-3 h-14 w-full rounded-full bg-rosso text-lg font-extrabold text-white" @click="simulate">
                {{ t('Testzahlung abschließen') }}
            </button>
        </div>

        <Link :href="route('guest.event', event.slug)" class="mt-6 inline-block py-2 text-grigio underline">{{ t('Zurück zur Karte') }}</Link>
    </GuestLayout>
</template>
