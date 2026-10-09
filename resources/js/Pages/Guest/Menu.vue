<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { euro } from '@/lib/money';
import { Head } from '@inertiajs/vue3';

defineProps({
    event: Object,
    items: Array,
});
</script>

<template>
    <Head title="Il menù" />
    <GuestLayout>
        <template v-if="event">
            <p class="mt-6 font-script text-3xl text-rosso">Il menù</p>
            <h1 class="font-display text-4xl font-bold">Was darf's sein?</h1>
            <p class="mt-1 text-grigio">{{ event.name }}</p>

            <ul class="mt-6 space-y-3">
                <li
                    v-for="item in items"
                    :key="item.id"
                    class="rounded-card bg-raised p-4"
                    :class="{ 'opacity-50': item.sold_out }"
                >
                    <div class="flex items-baseline justify-between gap-4">
                        <span class="font-display text-xl font-bold">{{ item.name }}</span>
                        <span class="font-display text-lg font-bold">{{ euro(item.price_cents) }}</span>
                    </div>
                    <p class="text-sm text-grigio">
                        <span v-if="item.variant">{{ item.variant }} · </span>{{ item.description }}
                    </p>
                    <p v-if="item.deposit_cents" class="text-sm text-grigio">+ {{ euro(item.deposit_cents) }} Pfand</p>
                </li>
            </ul>
        </template>
        <p v-else class="mt-10 font-display text-2xl">Gerade ist kein Markt geöffnet. Bis bald!</p>
    </GuestLayout>
</template>
