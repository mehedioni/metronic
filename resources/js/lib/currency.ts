import { ref } from 'vue';
import type { CurrencyConfig } from '@/types';

/**
 * The currency the store trades in, chosen in Settings → General.
 *
 * A single store trades in a single currency, so this is the only one there
 * is — no record carries a copy. A ref rather than a constant: every template
 * calling money() tracks it, so changing the setting re-renders every figure
 * without a reload. It is set from the shared Inertia props on boot and after
 * each visit (see app.ts), so nothing here knows how it was fetched.
 */
const store = ref<CurrencyConfig>({
    code: 'USD',
    name: 'US Dollar',
    symbol: '$',
    position: 'before',
    decimals: 2,
});

export function setCurrency(config?: CurrencyConfig | null): void {
    if (config?.code) {
        store.value = config;
    }
}

export function currency(): CurrencyConfig {
    return store.value;
}
