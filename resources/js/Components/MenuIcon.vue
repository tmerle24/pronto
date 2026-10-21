<script setup>
// Illustration per item, cropped to its drawing: the box is exactly as wide as the
// picture, so spacing around it is what the layout says. A slice shows one quarter
// in colour inside the faint whole pizza.
defineProps({
    icon: String,
    variant: String,
});
</script>

<template>
    <span v-if="icon" class="relative inline-block max-w-full shrink-0" aria-hidden="true">
        <img :src="`/illustrations/${icon}.svg`" alt="" class="block h-full w-auto max-w-full object-contain" :class="{ 'opacity-25': variant === 'slice' }" />
        <img
            v-if="variant === 'slice'"
            :src="`/illustrations/${icon}.svg`"
            alt=""
            class="absolute inset-0 h-full w-full object-contain"
            style="clip-path: polygon(50% 50%, 100% 0%, 100% 100%)"
        />
    </span>
</template>
