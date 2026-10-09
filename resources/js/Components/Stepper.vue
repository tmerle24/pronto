<script setup>
import { t } from '@/lib/i18n';

defineProps({ quantity: Number, name: String, disabled: Boolean });
defineEmits(['add', 'remove']);
</script>

<template>
    <div class="flex items-center gap-1">
        <button
            v-if="quantity > 0"
            type="button"
            class="grid size-12 place-items-center rounded-full bg-crema-scuro text-2xl font-bold text-carbone"
            :aria-label="t(':name weniger', { name })"
            @click="$emit('remove')"
        >
            -
        </button>
        <span v-if="quantity > 0" class="w-7 text-center font-display text-xl font-bold" aria-live="polite">{{ quantity }}</span>
        <button
            type="button"
            class="grid size-12 place-items-center rounded-full bg-rosso text-2xl font-bold text-white disabled:bg-crema-scuro disabled:text-grigio"
            :disabled="disabled"
            :aria-label="t(':name mehr', { name })"
            @click="$emit('add')"
        >
            +
        </button>
    </div>
</template>
