<script setup>
import Wordmark from '@/Components/Wordmark.vue';
import { t } from '@/lib/i18n';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
    <div class="min-h-dvh">
        <div class="trikolore h-2" aria-hidden="true"></div>
        <header class="mx-auto flex max-w-xl items-center justify-between px-4 pt-4">
            <Wordmark class="text-2xl text-carbone" />
            <a
                :href="`?lang=${page.props.locale === 'de' ? 'en' : 'de'}`"
                class="label-caps grid h-11 place-items-center px-2 text-grigio"
                :aria-label="page.props.locale === 'de' ? 'English' : 'Deutsch'"
            >
                {{ page.props.locale === 'de' ? 'EN' : 'DE' }}
            </a>
        </header>
        <main class="mx-auto max-w-xl px-4 pb-32">
            <slot />
        </main>
        <footer class="mx-auto flex max-w-xl gap-4 px-4 pb-8 text-sm text-grigio">
            <a :href="page.props.brand.legal.imprint_url" class="underline">{{ t('Impressum') }}</a>
            <a :href="page.props.brand.legal.privacy_url" class="underline">{{ t('Datenschutz') }}</a>
        </footer>
    </div>
</template>
