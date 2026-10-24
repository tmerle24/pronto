<script setup>
import Wordmark from '@/Components/Wordmark.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ token: String, name: String, valid: Boolean });

const form = useForm({ name: props.name ?? '', email: '', password: '', password_confirmation: '' });
</script>

<template>
    <Head title="Einladung" />
    <div class="grid min-h-dvh place-items-center bg-crema px-4 py-8">
        <form v-if="valid" class="w-full max-w-sm rounded-card bg-raised p-6" @submit.prevent="form.post(route('team.invitation', token))">
            <Wordmark class="text-3xl" />
            <p class="font-script text-2xl text-rosso">Benvenuto nella squadra!</p>
            <p class="mt-2 text-grigio">Leg deinen Zugang an. Danach trägst du ein, wann du am Markt helfen kannst.</p>

            <label class="label-caps mt-6 block text-grigio" for="name">Name</label>
            <input id="name" v-model="form.name" type="text" autocomplete="name" required class="mt-1 h-12 w-full rounded-xl border-2 border-crema-scuro px-3 text-lg" />
            <label class="label-caps mt-4 block text-grigio" for="email">E-Mail</label>
            <input id="email" v-model="form.email" type="email" autocomplete="username" required class="mt-1 h-12 w-full rounded-xl border-2 border-crema-scuro px-3 text-lg" />
            <label class="label-caps mt-4 block text-grigio" for="password">Passwort (mindestens 8 Zeichen)</label>
            <input id="password" v-model="form.password" type="password" autocomplete="new-password" required minlength="8" class="mt-1 h-12 w-full rounded-xl border-2 border-crema-scuro px-3 text-lg" />
            <label class="label-caps mt-4 block text-grigio" for="password_confirmation">Passwort wiederholen</label>
            <input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" required class="mt-1 h-12 w-full rounded-xl border-2 border-crema-scuro px-3 text-lg" />

            <p v-for="(message, key) in form.errors" :key="key" class="mt-3 font-semibold text-rosso" role="alert">{{ message }}</p>
            <button type="submit" class="mt-6 h-14 w-full rounded-full bg-rosso text-lg font-extrabold text-white disabled:opacity-60" :disabled="form.processing">
                Zugang anlegen
            </button>
        </form>

        <div v-else class="w-full max-w-sm rounded-card bg-raised p-6">
            <Wordmark class="text-3xl" />
            <p class="mt-4 font-display text-2xl font-bold">Der Link gilt nicht mehr.</p>
            <p class="mt-2 text-grigio">Er ist abgelaufen oder wurde schon benutzt. Bitte frag Timo nach einem neuen Link.</p>
            <Link :href="route('team.login')" class="mt-6 grid h-14 place-items-center rounded-full bg-crema-scuro text-lg font-bold">Zur Anmeldung</Link>
        </div>
    </div>
</template>
