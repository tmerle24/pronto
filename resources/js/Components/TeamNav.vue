<script setup>
import { unlockAudio } from '@/lib/sound';
import { keepAwake } from '@/lib/wakeLock';
import { Link } from '@inertiajs/vue3';

defineProps({ current: String });
</script>

<template>
    <nav class="flex rounded-full bg-crema-scuro p-1">
        <Link
            v-for="tab in [{ name: 'team.queue', label: 'Cucina' }, { name: 'team.counter', label: 'Cassa' }]"
            :key="tab.name"
            :href="route(tab.name)"
            @click="unlockAudio(); keepAwake()"
            class="grid h-11 place-items-center rounded-full px-4 font-script text-2xl"
            :class="current === tab.name ? 'bg-rosso text-white' : 'text-carbone'"
        >
            {{ tab.label }}
        </Link>
    </nav>
</template>
