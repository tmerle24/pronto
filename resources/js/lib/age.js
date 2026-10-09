export function minutesSince(iso, now) {
    if (!iso) return 0;
    return Math.max(0, Math.floor((now - new Date(iso).getTime()) / 60000));
}

export function ageLabel(iso, now) {
    const min = minutesSince(iso, now);
    return min < 1 ? 'gerade eben' : `vor ${min} Min`;
}
