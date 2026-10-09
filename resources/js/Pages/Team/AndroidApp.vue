<script setup>
import TeamNav from '@/Components/TeamNav.vue';
import Wordmark from '@/Components/Wordmark.vue';
import { Head } from '@inertiajs/vue3';

defineProps({ apk: Object });

const mb = (bytes) => (bytes / 1048576).toFixed(1).replace('.', ',');
const date = (iso) => new Date(iso).toLocaleDateString('de-DE');
</script>

<template>
    <Head title="App für die Cassa" />
    <div class="min-h-dvh bg-crema text-carbone">
        <header class="flex h-16 items-center gap-4 px-4">
            <Wordmark class="text-2xl" />
            <TeamNav current="team.app" />
        </header>

        <main class="mx-auto max-w-xl px-4 pb-16">
            <h1 class="mt-4 font-display text-4xl font-black">App für die Cassa</h1>
            <p class="mt-2 text-lg">
                Mit der Android-App nimmt das Theken-Handy Karten, Handys und Uhren direkt an (Tap to Pay). Die Bestellung ist danach
                automatisch bezahlt, ohne SumUp.
            </p>

            <section class="mt-6 rounded-card bg-raised p-5">
                <template v-if="apk">
                    <a :href="route('team.app.download')" class="grid h-[76px] place-items-center rounded-card bg-rosso text-xl font-extrabold text-white">
                        App herunterladen
                    </a>
                    <p class="mt-3 text-sm text-grigio">
                        Version {{ apk.version }} - {{ mb(apk.size) }} MB - vom {{ date(apk.published_at) }}
                    </p>
                    <p class="mt-1 text-xs break-all text-grigio">SHA-256 {{ apk.sha256 }}</p>
                </template>
                <p v-else class="text-lg">Die App ist noch nicht freigegeben. Bis dahin: Karte wie bisher über SumUp.</p>
            </section>

            <section class="mt-6">
                <h2 class="label-caps text-grigio">Installieren</h2>
                <ol class="mt-2 list-decimal space-y-2 pl-5 text-lg">
                    <li>Auf dem Theken-Handy diese Seite öffnen und "App herunterladen" tippen.</li>
                    <li>Download öffnen. Android fragt nach "Unbekannte Apps installieren": für den Browser erlauben, zurück, "Installieren".</li>
                    <li>App "TIAMO Pronto" öffnen, mit dem Team-Login anmelden. Standort erlauben (verlangt Stripe).</li>
                    <li>NFC einschalten: Einstellungen, "Verbundene Geräte" bzw. "Verbindungen", NFC an.</li>
                    <li>Entwickleroptionen müssen aus sein, sonst lehnt Stripe das Handy ab.</li>
                </ol>
            </section>

            <section class="mt-6">
                <h2 class="label-caps text-grigio">Voraussetzungen</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li>Android 13 oder neuer, Sicherheitsupdate nicht älter als 12 Monate</li>
                    <li>NFC eingebaut, Google Play Store vorhanden, nicht gerootet</li>
                    <li>Stabiles Internet (WLAN oder mobile Daten)</li>
                </ul>
                <p class="mt-3 text-grigio">Klappt Tap to Pay nicht, bleibt "Karte (Terminal)" mit SumUp wie bisher.</p>
            </section>
        </main>
    </div>
</template>
