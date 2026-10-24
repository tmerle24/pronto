<script setup>
import TeamNav from '@/Components/TeamNav.vue';
import Wordmark from '@/Components/Wordmark.vue';
import { euro } from '@/lib/money';
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({ days: Array });

// one small form per day, prefilled with the own entry or the whole working day
const forms = reactive(
    Object.fromEntries(
        props.days.map((d) => [
            d.id,
            { from: d.availability?.from ?? d.work_from, until: d.availability?.until ?? d.work_until, note: d.availability?.note ?? '', errors: {}, busy: false },
        ]),
    ),
);

function dateLabel(day) {
    const [y, m, d] = day.date.split('-');
    return `${day.weekday}, ${Number(d)}.${Number(m)}.${y}`;
}

function save(day) {
    const f = forms[day.id];
    f.busy = true;
    router.put(route('team.shifts.availability', day.id), { from: f.from, until: f.until, note: f.note || null }, {
        preserveScroll: true,
        onError: (errors) => (f.errors = errors),
        onSuccess: () => (f.errors = {}),
        onFinish: () => (f.busy = false),
    });
}

function remove(day) {
    forms[day.id].busy = true;
    router.delete(route('team.shifts.availability.destroy', day.id), { preserveScroll: true, onFinish: () => (forms[day.id].busy = false) });
}

const upcoming = props.days.filter((d) => !d.past);
const past = props.days.filter((d) => d.past).reverse();
</script>

<template>
    <Head title="Einsätze" />
    <div class="min-h-dvh bg-crema text-carbone">
        <header class="flex h-16 items-center gap-3 px-3 md:gap-4 md:px-4">
            <Wordmark class="hidden text-2xl sm:inline" />
            <TeamNav current="team.shifts" />
        </header>

        <main class="mx-auto grid max-w-2xl gap-6 px-3 pb-10 md:px-4">
            <div>
                <p class="font-script text-3xl text-rosso">La squadra</p>
                <h1 class="font-display text-3xl font-bold">Wann kannst du?</h1>
                <p class="mt-1 text-grigio">Trag für jeden Markttag ein, von wann bis wann du kannst. Timo plant danach, wer kommt.</p>
            </div>

            <p v-if="!upcoming.length" class="rounded-card bg-raised p-4 text-grigio">Gerade sind keine Markttage geplant.</p>

            <section v-for="day in upcoming" :key="day.id" class="grid gap-4 rounded-card bg-raised p-4">
                <div>
                    <p class="label-caps text-grigio">{{ dateLabel(day) }}</p>
                    <h2 class="font-display text-2xl font-bold">{{ day.event }}</h2>
                    <p class="text-grigio">
                        Geöffnet {{ day.opens }}–{{ day.closes }} Uhr<template v-if="day.work_from !== day.opens || day.work_until !== day.closes">
                            · Aufbau ab {{ day.work_from }}, Abbau bis {{ day.work_until }}</template
                        ><template v-if="day.location"> · {{ day.location }}</template>
                    </p>
                </div>

                <!-- the plan, once Timo has made it -->
                <div v-if="day.team.length" class="rounded-card bg-crema p-3">
                    <p class="label-caps text-grigio">Eingeplant</p>
                    <ul class="mt-1 grid gap-1">
                        <li v-for="(s, i) in day.team" :key="i" class="flex flex-wrap gap-x-2" :class="{ 'font-bold text-basilico': s.mine }">
                            <span class="tabular-nums">{{ s.from }}–{{ s.until }}</span>
                            <span>{{ s.mine ? 'Du' : s.name }}</span>
                            <span v-if="s.task" class="text-grigio">· {{ s.task }}</span>
                        </li>
                    </ul>
                </div>

                <form class="grid gap-3" @submit.prevent="save(day)">
                    <p class="font-semibold">
                        <template v-if="day.availability">Du kannst {{ day.availability.from }}–{{ day.availability.until }} Uhr.</template>
                        <template v-else>Noch nicht eingetragen.</template>
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="grid gap-1">
                            <span class="label-caps text-grigio">Von</span>
                            <input v-model="forms[day.id].from" type="time" step="900" required class="h-12 rounded-xl border-2 border-crema-scuro px-3 text-lg tabular-nums" />
                        </label>
                        <label class="grid gap-1">
                            <span class="label-caps text-grigio">Bis</span>
                            <input v-model="forms[day.id].until" type="time" step="900" required class="h-12 rounded-xl border-2 border-crema-scuro px-3 text-lg tabular-nums" />
                        </label>
                    </div>
                    <label class="grid gap-1">
                        <span class="label-caps text-grigio">Notiz (optional)</span>
                        <input v-model="forms[day.id].note" type="text" maxlength="200" placeholder="z. B. nur mit Auto, ab 14 Uhr flexibel" class="h-12 rounded-xl border-2 border-crema-scuro px-3" />
                    </label>
                    <p v-for="(message, key) in forms[day.id].errors" :key="key" class="font-semibold text-rosso" role="alert">{{ message }}</p>
                    <div class="grid grid-cols-2 gap-3">
                        <button type="submit" class="h-14 rounded-card bg-basilico text-lg font-extrabold text-white disabled:opacity-50" :disabled="forms[day.id].busy">
                            {{ day.availability ? 'Ändern' : 'Ich kann' }}
                        </button>
                        <button
                            type="button"
                            class="h-14 rounded-card bg-crema-scuro text-lg font-bold disabled:opacity-50"
                            :disabled="forms[day.id].busy || !day.availability"
                            @click="remove(day)"
                        >
                            Ich kann nicht
                        </button>
                    </div>
                </form>
            </section>

            <section v-if="past.length" class="grid gap-3">
                <h2 class="font-display text-2xl font-bold">Deine Einsätze</h2>
                <div v-for="day in past" :key="day.id" class="flex items-center justify-between gap-3 rounded-card bg-raised p-4">
                    <div class="min-w-0">
                        <p class="label-caps text-grigio">{{ dateLabel(day) }}</p>
                        <p class="font-semibold">{{ day.event }}</p>
                    </div>
                    <div v-if="day.payout" class="shrink-0 text-right">
                        <p class="font-display text-2xl font-bold tabular-nums">{{ euro(day.payout.cents) }}</p>
                        <p class="text-sm text-grigio">{{ day.payout.hours.toLocaleString('de-DE') }} h · {{ day.payout.settled ? 'abgerechnet' : 'vorläufig' }}</p>
                    </div>
                    <p v-else class="shrink-0 text-grigio">nicht dabei</p>
                </div>
            </section>
        </main>
    </div>
</template>
