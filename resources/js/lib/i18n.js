import { usePage } from '@inertiajs/vue3';

// German text is the key; lang/en.json holds the English version.
export function t(key, replace = {}) {
    const dict = usePage().props.translations ?? {};
    let text = dict[key] ?? key;
    for (const [name, value] of Object.entries(replace)) {
        text = text.replaceAll(`:${name}`, value);
    }
    return text;
}

export function variantLabel(variant) {
    return { whole: t('ganz'), slice: t('Stück'), gratinated: t('gratiniert') }[variant] ?? '';
}
