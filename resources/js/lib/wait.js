import { t } from './i18n';

// always a range, never an exact minute
export function waitLabel(wait) {
    if (!wait) return '';
    if (wait.low === 0) return t('unter :high Min', { high: wait.high });
    return t('ca. :low bis :high Min', { low: wait.low, high: wait.high });
}

export function aheadLabel(ahead) {
    if (ahead === 0) return t('Du bist als Nächstes dran');
    if (ahead === 1) return t('1 Bestellung vor dir');
    return t(':n Bestellungen vor dir', { n: ahead });
}
