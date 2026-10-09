<script setup>
import { t } from '@/lib/i18n';
import { euro } from '@/lib/money';

defineProps({ receipt: Object, token: String });

const rate = (r) => `${String(r).replace('.', ',')} %`;
</script>

<template>
    <details class="rounded-card bg-raised p-4">
        <summary class="flex h-11 cursor-pointer items-center justify-between font-semibold">
            <span>{{ t('Beleg') }} {{ receipt.number }}</span>
            <span class="label-caps text-grigio">{{ t('anzeigen') }}</span>
        </summary>
        <p class="mt-2 text-sm text-grigio">{{ receipt.business.name }} · {{ receipt.business.address }}</p>
        <p class="text-sm text-grigio">{{ receipt.date }} · {{ receipt.payment }}</p>
        <p v-if="receipt.refunded" class="label-caps mt-2 text-rosso">{{ t('Storniert und erstattet') }}</p>
        <table class="mt-3 w-full text-sm">
            <tbody>
                <tr v-for="(line, i) in receipt.lines" :key="i">
                    <td class="py-1">{{ line.quantity }}× {{ line.name }}</td>
                    <td class="py-1 text-right text-grigio">{{ rate(line.vat_rate) }}</td>
                    <td class="py-1 text-right">{{ euro(line.total_cents) }}</td>
                </tr>
                <tr class="border-t-2 border-carbone font-bold">
                    <td class="py-1">{{ t('Summe') }}</td>
                    <td></td>
                    <td class="py-1 text-right">{{ euro(receipt.total_cents) }}</td>
                </tr>
                <tr v-for="g in receipt.vat" :key="g.rate" class="text-grigio">
                    <td class="py-0.5" colspan="2">{{ t('enth. MwSt.') }} {{ rate(g.rate) }} ({{ t('netto') }} {{ euro(g.net_cents) }})</td>
                    <td class="py-0.5 text-right">{{ euro(g.vat_cents) }}</td>
                </tr>
            </tbody>
        </table>
        <a :href="route('guest.order.receipt', token)" class="mt-3 flex h-12 items-center justify-center rounded-full border-2 border-carbone font-bold">
            {{ t('Beleg als PDF') }}
        </a>
    </details>
</template>
