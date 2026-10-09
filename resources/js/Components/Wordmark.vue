<script setup>
import { usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
    <span class="font-display font-black tracking-tight">
        {{ page.props.brand.wordmark[0] }}<span class="text-oro" aria-hidden="true">♥</span>{{ page.props.brand.wordmark[1] }}
    </span>
</template>
