<script setup>
import Wordmark from '@/Components/Wordmark.vue';
import { useEcho } from '@/lib/echo';
import { getJson, NetworkError } from '@/lib/http';
import { chime, unlockAudio } from '@/lib/sound';
import { waitLabel } from '@/lib/wait';
import { keepAwake } from '@/lib/wakeLock';
import { Head, usePage } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    event: Object,
    board: Object,
    wait: Object,
});

const page = usePage();
const inOven = ref(props.board.in_oven);
const ready = ref(props.board.ready);
const fresh = ref(new Set());
const started = ref(false);
const gong = ref(true);
const wait = ref(props.wait);

function apply(board) {
    const before = new Set(ready.value);
    const added = board.ready.filter((n) => !before.has(n));
    inOven.value = board.in_oven;
    ready.value = board.ready;
    if (added.length) {
        fresh.value = new Set(added);
        setTimeout(() => (fresh.value = new Set()), 4000);
        if (gong.value && started.value) chime([660, 880, 1100], 0.35);
    }
}

async function poll() {
    try {
        const state = await getJson(route('display.state', props.event.token));
        apply(state.board);
        wait.value = state.wait;
    } catch (e) {
        if (!(e instanceof NetworkError)) throw e;
    }
}

async function start() {
    unlockAudio();
    started.value = true;
    await keepAwake();
    document.documentElement.requestFullscreen?.().catch(() => {});
}

let timer = null;
onMounted(() => {
    const echo = useEcho(page.props.realtime);
    echo?.channel(`board.${props.event.token}`).listen('.board.updated', (e) => apply(e.board));
    echo?.channel(`wait.${props.event.id}`).listen('.wait.updated', (e) => (wait.value = e.general));
    // fallback and self-healing
    timer = setInterval(poll, 15000);
});
onUnmounted(() => clearInterval(timer));
</script>

<template>
    <Head title="Tabellone" />
    <div class="flex h-dvh flex-col bg-notte text-crema select-none">
        <div class="trikolore h-2 shrink-0" aria-hidden="true"></div>
        <header class="flex items-center justify-between px-8 py-4">
            <Wordmark class="text-4xl text-crema" />
            <span class="label-caps text-base text-oro">{{ event.name }}</span>
        </header>

        <main class="grid min-h-0 flex-1 grid-cols-[2fr_3fr] gap-6 px-8 pb-4">
            <section class="flex min-h-0 flex-col">
                <h2 class="font-script text-5xl text-oro">Nel forno</h2>
                <p class="label-caps mt-1 text-crema/80">Im Ofen und in Arbeit</p>
                <ul class="mt-6 flex flex-wrap content-start gap-4 overflow-hidden">
                    <li v-for="n in inOven" :key="n" class="min-w-24 rounded-card bg-crema/10 px-4 py-2 text-center font-display text-5xl font-bold">{{ n }}</li>
                </ul>
            </section>

            <section class="flex min-h-0 flex-col rounded-card bg-rosso p-6">
                <h2 class="font-script text-6xl text-white">Pronto! Abholen</h2>
                <p class="label-caps mt-1 text-white/90">Zeig deine Nummer an der Ausgabe</p>
                <ul class="mt-6 flex flex-wrap content-start gap-5 overflow-hidden">
                    <li
                        v-for="n in ready"
                        :key="n"
                        class="min-w-36 rounded-card bg-crema px-6 py-3 text-center font-display text-8xl font-black text-rosso"
                        :class="{ 'animate-bounce': fresh.has(n) }"
                    >
                        {{ n }}
                    </li>
                </ul>
            </section>
        </main>

        <footer class="flex items-center justify-between gap-6 border-t border-crema/15 px-8 py-4 text-xl">
            <span class="label-caps text-base">Scan &amp; bestell: QR-Code am Stand</span>
            <span v-if="wait" class="label-caps text-base text-oro">Wartezeit gerade {{ waitLabel(wait) }}</span>
            <span class="font-semibold">{{ page.props.brand.instagram }}</span>
        </footer>

        <button v-if="!started" type="button" class="fixed inset-0 z-30 flex flex-col items-center justify-center gap-4 bg-notte/95" @click="start">
            <span class="font-display text-5xl font-bold">Tippen zum Starten</span>
            <span class="text-xl text-crema/80">Gong an, Bildschirm bleibt an.</span>
        </button>
    </div>
</template>
