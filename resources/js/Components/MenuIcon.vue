<script setup>
// Illustration per item; a slice shows one quarter in colour inside the faint whole pizza.
defineProps({
    icon: String,
    variant: String,
});
</script>

<template>
    <div v-if="icon" class="relative shrink-0" aria-hidden="true">
        <template v-if="variant === 'slice'">
            <img :src="`/illustrations/${icon}.svg`" alt="" class="size-full object-contain opacity-25" />
            <img
                :src="`/illustrations/${icon}.svg`"
                alt=""
                class="absolute inset-0 size-full object-contain"
                style="clip-path: polygon(50% 50%, 100% 0%, 100% 100%)"
            />
        </template>
        <img v-else :src="`/illustrations/${icon}.svg`" alt="" class="size-full object-contain" />
    </div>
</template>
