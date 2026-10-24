<script setup>
import { NetworkError, postJson } from '@/lib/http';
import { computed, ref } from 'vue';

const props = defineProps({
    status: Object,
    items: Array,
    pauseOptions: Array,
    now: Number,
});
const emit = defineEmits(['update:status', 'error']);

const panel = ref(null);
const busy = ref(false);

const pausedMinutes = computed(() => {
    if (!props.status?.paused_until) return 0;
    return Math.max(0, Math.ceil((new Date(props.status.paused_until).getTime() - props.now) / 60000));
});
const soldOut = computed(() => new Set(props.status?.sold_out ?? []));

async function send(url, body) {
    busy.value = true;
    try {
        const { status, data } = await postJson(url, body);
        if (status === 200) emit('update:status', data.status);
        else emit('error', 'Hat nicht geklappt.');
    } catch (e) {
        if (!(e instanceof NetworkError)) throw e;
        emit('error', 'Offline - bitte gleich nochmal.');
    } finally {
        busy.value = false;
    }
}

function pause(minutes) {
    panel.value = null;
    send(route('team.intake.pause'), { minutes });
}

function setAccepting(accepting) {
    send(route('team.intake.accepting'), { accepting });
}

function toggleSoldOut(item) {
    send(route('team.menu-items.sold-out', item.id), { sold_out: !soldOut.value.has(item.id) });
}

const variantLabel = (v) => ({ whole: 'ganz', slice: 'Stück', gratinated: 'gratiniert' })[v] ?? '';
</script>

<template>
    <div class="flex items-center gap-2">
        <button
            v-if="pausedMinutes > 0"
            type="button"
            class="h-12 rounded-full bg-oro px-4 font-bold text-carbone"
            :disabled="busy"
            @click="pause(0)"
        >
            Pause noch {{ pausedMinutes }} Min · Weiter
        </button>
        <button v-else type="button" class="h-12 rounded-full bg-raised px-4 font-bold" @click="panel = 'pause'">
            {{ status?.accepting_orders === false ? 'Annahme aus' : 'Pause' }}
        </button>
        <button type="button" class="h-12 rounded-full bg-raised px-4 font-bold" @click="panel = 'soldout'">
            Ausverkauft<span v-if="soldOut.size" class="ml-2 rounded-full bg-rosso px-2 text-white">{{ soldOut.size }}</span>
        </button>
    </div>

    <div v-if="panel" class="fixed inset-0 z-30 flex items-start justify-end bg-carbone/40 p-4" @click.self="panel = null">
        <div class="mt-16 w-full max-w-[420px] rounded-card bg-crema p-5 shadow-2xl">
            <template v-if="panel === 'pause'">
                <h2 class="font-display text-2xl font-bold">Ofen voll? Pause.</h2>
                <p class="text-grigio">Gäste sehen "Kurze Pause", die Wartezeit steigt.</p>
                <div class="mt-4 grid grid-cols-3 gap-3">
                    <button
                        v-for="minutes in pauseOptions"
                        :key="minutes"
                        type="button"
                        class="h-[76px] rounded-card bg-oro text-2xl font-extrabold text-carbone"
                        @click="pause(minutes)"
                    >
                        {{ minutes }} Min
                    </button>
                </div>
                <button
                    type="button"
                    class="mt-4 h-[76px] w-full rounded-card text-xl font-extrabold"
                    :class="status?.accepting_orders ? 'bg-carbone text-crema' : 'bg-basilico text-white'"
                    :disabled="busy"
                    @click="setAccepting(!status?.accepting_orders)"
                >
                    {{ status?.accepting_orders ? 'Bestellannahme ausschalten' : 'Bestellannahme einschalten' }}
                </button>
            </template>

            <template v-else>
                <h2 class="font-display text-2xl font-bold">Ausverkauft</h2>
                <p class="text-grigio">Tippen schaltet um. Gäste können es sofort nicht mehr bestellen.</p>
                <ul class="mt-4 space-y-2">
                    <li v-for="item in items" :key="item.id">
                        <button
                            type="button"
                            class="flex h-16 w-full items-center justify-between rounded-card px-4 text-lg font-bold"
                            :class="soldOut.has(item.id) ? 'bg-rosso text-white' : 'bg-raised text-carbone'"
                            :disabled="busy"
                            @click="toggleSoldOut(item)"
                        >
                            <span>{{ item.name }} {{ variantLabel(item.variant) }}</span>
                            <span class="label-caps">{{ soldOut.has(item.id) ? 'Ausverkauft' : 'Verfügbar' }}</span>
                        </button>
                    </li>
                </ul>
            </template>
        </div>
    </div>
</template>
