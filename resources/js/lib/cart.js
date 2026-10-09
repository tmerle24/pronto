import { computed, reactive, watch } from 'vue';

// Cart per event, kept in localStorage so "back" from the pay page keeps it.
export function useCart(slug, items) {
    const key = `pronto.cart.${slug}`;
    const quantities = reactive(load());

    function load() {
        try {
            const saved = JSON.parse(localStorage.getItem(key) ?? '{}');
            const valid = new Set(items.filter((i) => !i.sold_out).map((i) => String(i.id)));
            return Object.fromEntries(Object.entries(saved).filter(([id, q]) => valid.has(id) && q > 0));
        } catch {
            return {};
        }
    }

    function save() {
        try {
            localStorage.setItem(key, JSON.stringify(quantities));
        } catch {
            // private mode: cart lives only in memory
        }
    }

    watch(quantities, save);

    const byId = Object.fromEntries(items.map((i) => [String(i.id), i]));

    const count = computed(() => Object.values(quantities).reduce((sum, q) => sum + q, 0));
    const totalCents = computed(() =>
        Object.entries(quantities).reduce((sum, [id, q]) => {
            const item = byId[id];
            return item ? sum + (item.price_cents + item.deposit_cents) * q : sum;
        }, 0),
    );
    const lines = computed(() =>
        Object.entries(quantities)
            .filter(([, q]) => q > 0)
            .map(([id, quantity]) => ({ id: Number(id), quantity })),
    );

    function add(id) {
        quantities[id] = Math.min((quantities[id] ?? 0) + 1, 20);
    }

    function remove(id) {
        const next = (quantities[id] ?? 0) - 1;
        if (next > 0) quantities[id] = next;
        else delete quantities[id];
    }

    // runs after navigation, when the watcher is already stopped
    function clear() {
        for (const id of Object.keys(quantities)) delete quantities[id];
        save();
    }

    return { quantities, count, totalCents, lines, add, remove, clear };
}
