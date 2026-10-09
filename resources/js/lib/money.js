const formatter = new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' });

export function euro(cents) {
    return formatter.format(cents / 100);
}
