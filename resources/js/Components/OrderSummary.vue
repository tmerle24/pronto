<script setup>
import { t, variantLabel } from '@/lib/i18n';
import { euro } from '@/lib/money';

defineProps({ order: Object });
</script>

<template>
    <ul class="divide-y divide-crema-scuro rounded-card bg-raised px-4">
        <li v-for="(item, i) in order.items" :key="i" class="flex justify-between gap-3 py-3">
            <span class="flex gap-1.5">
                <span class="shrink-0 tabular-nums">{{ item.quantity }}&#8239;×</span>
                <span>{{ item.name }} {{ variantLabel(item.variant) }}</span>
            </span>
            <span class="font-semibold">{{ euro(item.line_total_cents) }}</span>
        </li>
        <li class="flex justify-between py-3 font-bold">
            <span>{{ t('Summe') }}</span>
            <span>{{ euro(order.total_cents) }}</span>
        </li>
    </ul>
</template>
