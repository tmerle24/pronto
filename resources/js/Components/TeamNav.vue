<script setup>
import { unlockAudio } from '@/lib/sound';
import { keepAwake } from '@/lib/wakeLock';
import { Link } from '@inertiajs/vue3';

defineProps({ current: String });
</script>

<template>
    <nav class="flex rounded-full bg-crema-scuro p-1">
        <Link
            v-for="tab in [{ name: 'team.queue', label: 'Cucina' }, { name: 'team.counter', label: 'Cassa' }, { name: 'team.shifts', label: 'Turni' }]"
            :key="tab.name"
            :href="route(tab.name)"
            @click="unlockAudio(); keepAwake()"
            class="grid h-11 place-items-center rounded-full px-3 font-script text-2xl md:px-4"
            :class="current === tab.name ? 'bg-rosso text-white' : 'text-carbone'"
        >
            {{ tab.label }}
        </Link>
    </nav>
</template>
