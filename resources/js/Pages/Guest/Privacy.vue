<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
    <Head title="Datenschutz" />
    <GuestLayout>
        <article class="prose mt-6 space-y-4">
            <p class="rounded-card bg-oro p-3 font-semibold text-carbone">Entwurf - vor dem ersten Markttag rechtlich prüfen lassen.</p>
            <h1 class="font-display text-4xl font-bold">Datenschutz bei TIAMO Pronto</h1>
            <p>
                Pronto ist unser Bestellsystem am Stand. Verantwortlich ist der Betreiber von TIAMO Pizza, siehe
                <a :href="page.props.brand.legal.imprint_url" class="underline">Impressum</a>. Die allgemeine Datenschutzerklärung von
                <a href="https://www.tiamo-pizza.de/datenschutz" class="underline">tiamo-pizza.de</a> gilt ergänzend.
            </p>
            <h2 class="font-display text-2xl font-bold">Was wir speichern</h2>
            <ul class="list-disc space-y-1 pl-5">
                <li>Deine Bestellung (Artikel, Summe, Zeitpunkte, Abholnummer). Kein Konto, kein Name, keine E-Mail.</li>
                <li>Bei Online-Zahlung wickelt Stripe (Stripe Payments Europe Ltd., Irland) die Zahlung ab. Wir sehen keine Kartendaten.</li>
                <li>Nur wenn du es aktiv auswählst: Handynummer (SMS), WhatsApp-Nummer oder eine Push-Adresse deines Browsers, um dir zu sagen, dass deine Pizza fertig ist. Diese Daten löschen wir automatisch nach 24 Stunden.</li>
                <li>Für Belege und Steuer bewahren wir Bestellungen und Zahlungen so lange auf, wie es das Gesetz verlangt.</li>
            </ul>
            <h2 class="font-display text-2xl font-bold">Was wir nicht tun</h2>
            <ul class="list-disc space-y-1 pl-5">
                <li>Keine Tracker, keine Werbung, keine Analyse-Tools.</li>
                <li>Nur technisch notwendige Cookies (Sitzung, Sicherheit, Sprache) und ein Warenkorb im Speicher deines Browsers.</li>
            </ul>
            <h2 class="font-display text-2xl font-bold">Deine Rechte</h2>
            <p>Du kannst Auskunft, Berichtigung und Löschung verlangen und dich bei einer Aufsichtsbehörde beschweren. Schreib uns einfach, Kontakt siehe Impressum.</p>
        </article>
    </GuestLayout>
</template>
