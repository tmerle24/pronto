<script setup>
import Wordmark from '@/Components/Wordmark.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({ email: '', password: '' });
</script>

<template>
    <Head title="Team" />
    <div class="grid min-h-dvh place-items-center bg-crema px-4">
        <form class="w-full max-w-sm rounded-card bg-raised p-6" @submit.prevent="form.post(route('team.login'))">
            <Wordmark class="text-3xl" />
            <p class="font-script text-2xl text-rosso">La squadra</p>
            <label class="label-caps mt-6 block text-grigio" for="email">E-Mail</label>
            <input id="email" v-model="form.email" type="email" autocomplete="username" required class="mt-1 h-12 w-full rounded-xl border-2 border-crema-scuro px-3 text-lg" />
            <label class="label-caps mt-4 block text-grigio" for="password">Passwort</label>
            <input id="password" v-model="form.password" type="password" autocomplete="current-password" required class="mt-1 h-12 w-full rounded-xl border-2 border-crema-scuro px-3 text-lg" />
            <p v-if="form.errors.email" class="mt-3 font-semibold text-rosso" role="alert">{{ form.errors.email }}</p>
            <button type="submit" class="mt-6 h-14 w-full rounded-full bg-rosso text-lg font-extrabold text-white disabled:opacity-60" :disabled="form.processing">
                Anmelden
            </button>
            <p class="mt-3 text-sm text-grigio">Du bleibst auf diesem Gerät angemeldet.</p>
        </form>
    </div>
</template>
