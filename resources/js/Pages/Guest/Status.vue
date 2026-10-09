<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import OrderSummary from '@/Components/OrderSummary.vue';
import Receipt from '@/Components/Receipt.vue';
import { connected, useEcho } from '@/lib/echo';
import { postJson } from '@/lib/http';
import { t } from '@/lib/i18n';
import { euro } from '@/lib/money';
import { startPronto } from '@/lib/pronto';
import { pushSupported, registerServiceWorker, subscribePush } from '@/lib/push';
import { audioContext, unlockAudio } from '@/lib/sound';
import { aheadLabel, waitLabel } from '@/lib/wait';
import { keepAwake } from '@/lib/wakeLock';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    order: Object,
    event: Object,
    wait: Object,
    vapid_public_key: String,
    receipt: Object,
});

// live copy, replaced by websocket pushes and polling
const live = ref(props.order);
const order = computed(() => live.value);
const wait = ref(props.wait);
const receipt = ref(props.receipt);

const finished = computed(() => ['picked_up', 'cancelled'].includes(order.value.status));
const awaitingPayment = computed(() => !order.value.queued && order.value.status !== 'cancelled');
const open = computed(() => order.value.queued && ['new', 'in_progress'].includes(order.value.status));
const has = (channel) => order.value.channels?.includes(channel);

const statusText = computed(() => ({
    new: t('Bestellung ist angekommen'),
    in_progress: t('Deine Pizza ist im Ofen'),
    ready: t('Pronto! Deine Pizza ist fertig.'),
    picked_up: t('Buon appetito!'),
    cancelled: t('Bestellung storniert'),
})[order.value.status]);

// sound needs one tap on this page load (iOS), e.g. after the Stripe redirect
const soundOn = ref(!!audioContext());
function enableSound() {
    unlockAudio();
    soundOn.value = true;
    keepAwake();
}

// "Pronto!" moment
const showPronto = ref(false);
let stop = null;
function pronto() {
    showPronto.value = true;
    stop?.();
    stop = startPronto(order.value.number);
}
function closePronto() {
    showPronto.value = false;
    stop?.();
}
watch(
    () => order.value.status,
    (status, before) => {
        if (status === 'ready' && before !== 'ready') pronto();
        if (status !== 'ready') closePronto();
    },
);
// receipt appears once paid (cash at pickup, late webhook)
watch(
    () => [order.value.payment_status, order.value.status],
    () => router.reload({ only: ['receipt'], onSuccess: (page) => (receipt.value = page.props.receipt) }),
);

// channels
const canPush = ref(false);
const pushBusy = ref(false);
const pushDenied = ref(false);
async function enablePush() {
    enableSound();
    pushBusy.value = true;
    try {
        const subscription = await subscribePush(props.vapid_public_key);
        if (!subscription) {
            pushDenied.value = true;
            return;
        }
        const { status } = await postJson(route('guest.order.push', order.value.token), subscription);
        if (status === 200) live.value = { ...live.value, channels: [...(live.value.channels ?? []), 'webpush'] };
    } catch {
        pushDenied.value = true;
    } finally {
        pushBusy.value = false;
    }
}

const smsOpen = ref(false);
const smsForm = useForm({ phone: '', consent: false });
function submitSms() {
    smsForm.post(route('guest.order.sms', order.value.token), {
        preserveScroll: true,
        onSuccess: (page) => {
            live.value = page.props.order;
            smsOpen.value = false;
            smsForm.reset();
        },
    });
}

let timer = null;
onMounted(() => {
    keepAwake();
    canPush.value = pushSupported() && !!props.vapid_public_key;
    if (canPush.value) registerServiceWorker();
    document.addEventListener('pointerdown', enableSound, { once: true });

    const echo = useEcho(usePage().props.realtime);
    echo?.channel(`order.${props.order.token}`).listen('.order.updated', (e) => (live.value = e.order));
    echo?.channel(`wait.${props.event.id}`).listen('.wait.updated', (e) => {
        if (order.value.number) wait.value = e.orders[order.value.number] ?? null;
    });
    // websocket first; polling every 10 s while it is not connected
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

    if (order.value.status === 'ready') pronto();
});
onUnmounted(() => {
    clearInterval(timer);
    stop?.();
});
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

            <div v-if="wait && open" class="mt-4 rounded-card bg-raised p-4" aria-live="polite">
                <p class="font-display text-3xl font-bold">{{ waitLabel(wait) }}</p>
                <p class="text-grigio">{{ aheadLabel(wait.ahead) }}</p>
            </div>

            <p v-if="order.payment_method === 'cash' && order.payment_status === 'pending'" class="mt-4 rounded-card bg-oro p-3 font-semibold text-carbone">
                {{ t('Bitte bezahl :sum bar bei der Abholung.', { sum: euro(order.total_cents) }) }}
            </p>

            <template v-if="open">
                <p class="mt-6 text-lg">{{ t('Lass diese Seite offen. Wir klingeln, wenn deine Pizza fertig ist.') }}</p>

                <button
                    v-if="!soundOn"
                    type="button"
                    class="mt-3 h-12 w-full rounded-full border-2 border-carbone font-bold"
                    @click="enableSound"
                >
                    {{ t('Ton einschalten') }}
                </button>

                <div class="mt-6 space-y-3">
                    <p v-if="order.channels?.length" class="rounded-card bg-raised p-3 font-semibold text-basilico" role="status">
                        ✓ {{ t('Wir sagen dir zusätzlich Bescheid per :channels.', { channels: order.channels.map((c) => ({ webpush: t('Push'), whatsapp: 'WhatsApp', sms: 'SMS' })[c]).join(', ') }) }}
                    </p>

                    <a
                        v-if="order.whatsapp_link && !has('whatsapp')"
                        :href="order.whatsapp_link"
                        target="_blank"
                        rel="noopener"
                        class="flex h-14 w-full items-center justify-center rounded-full bg-basilico text-lg font-extrabold text-white"
                        @click="enableSound"
                    >
                        {{ t('Per WhatsApp Bescheid geben') }}
                    </a>

                    <button
                        v-if="canPush && !has('webpush') && !pushDenied"
                        type="button"
                        class="h-14 w-full rounded-full bg-carbone text-lg font-bold text-white disabled:opacity-60"
                        :disabled="pushBusy"
                        @click="enablePush"
                    >
                        {{ t('Benachrichtigung aufs Handy') }}
                    </button>

                    <template v-if="!has('sms')">
                        <button
                            v-if="!smsOpen"
                            type="button"
                            class="h-14 w-full rounded-full border-2 border-carbone text-lg font-bold"
                            @click="smsOpen = true"
                        >
                            {{ t('Per SMS') }}
                        </button>
                        <form v-else class="rounded-card bg-raised p-4" @submit.prevent="submitSms">
                            <label for="phone" class="label-caps text-grigio">{{ t('Deine Handynummer') }}</label>
                            <input
                                id="phone"
                                v-model="smsForm.phone"
                                type="tel"
                                inputmode="tel"
                                autocomplete="tel"
                                placeholder="0171 1234567"
                                class="mt-1 h-12 w-full rounded-xl border-2 border-crema-scuro px-3 text-lg"
                                required
                            />
                            <label class="mt-3 flex gap-3 text-sm">
                                <input v-model="smsForm.consent" type="checkbox" class="mt-0.5 size-5 shrink-0" required />
                                <span>{{ t('Ja, schickt mir eine SMS, wenn meine Bestellung fertig ist. Die Nummer wird nur dafür genutzt und nach 24 Stunden gelöscht.') }}</span>
                            </label>
                            <p v-if="smsForm.errors.phone || smsForm.errors.consent" class="mt-2 font-semibold text-rosso" role="alert">
                                {{ smsForm.errors.phone || smsForm.errors.consent }}
                            </p>
                            <button type="submit" class="mt-3 h-12 w-full rounded-full bg-rosso font-extrabold text-white disabled:opacity-60" :disabled="smsForm.processing">
                                {{ t('SMS-Benachrichtigung aktivieren') }}
                            </button>
                        </form>
                    </template>
                </div>
            </template>
        </template>

        <h2 class="label-caps mt-8 text-grigio">{{ t('Deine Bestellung') }}</h2>
        <OrderSummary :order="order" class="mt-2" />
        <Receipt v-if="receipt" :receipt="receipt" :token="order.token" class="mt-3" />

        <div
            v-if="showPronto"
            class="fixed inset-0 z-40 flex flex-col items-center justify-center bg-rosso px-6 text-center text-white"
            role="alertdialog"
            aria-live="assertive"
            @click="closePronto"
        >
            <p class="font-script text-7xl text-crema">Pronto!</p>
            <p class="font-display text-[11rem] leading-none font-black">{{ order.number }}</p>
            <p class="mt-4 font-display text-3xl font-bold">{{ t('Deine Pizza ist fertig.') }}</p>
            <p class="mt-2 text-xl">{{ t('Komm zur Ausgabe. Buon appetito!') }}</p>
            <button type="button" class="mt-10 h-14 rounded-full bg-crema px-10 text-lg font-extrabold text-rosso">{{ t('Alles klar') }}</button>
        </div>
    </GuestLayout>
</template>
