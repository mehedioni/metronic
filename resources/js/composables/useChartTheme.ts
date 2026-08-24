import { computed, ref } from 'vue';
import { useAppearance } from '@/composables/useAppearance';

/**
 * ApexCharts options derived from the CSS design tokens.
 *
 * Apex renders into its own SVG and takes colours as JavaScript values, so it
 * cannot inherit from the stylesheet. Reading the custom properties back off
 * the root element keeps one source of truth: change a token in app.css and
 * the charts follow.
 */
export function useChartTheme() {
    const { resolvedAppearance } = useAppearance();

    /** Bumped on theme change to re-read the computed custom properties. */
    const revision = ref(0);

    function token(name: string, fallback: string): string {
        if (typeof window === 'undefined') {
            return fallback;
        }

        const value = getComputedStyle(document.documentElement)
            .getPropertyValue(name)
            .trim();

        return value || fallback;
    }

    const isDark = computed(() => resolvedAppearance.value === 'dark');

    const palette = computed(() => {
        void revision.value;

        return [
            token('--chart-1', '#3b82f6'),
            token('--chart-2', '#f59e0b'),
            token('--chart-3', '#8b5cf6'),
            token('--chart-4', '#10b981'),
            token('--chart-5', '#f43f5e'),
        ];
    });

    /** Options every chart in the app shares. */
    const base = computed(() => {
        void revision.value;

        const muted = token('--muted-foreground', '#71717a');
        const border = token('--border', '#e4e4e7');

        return {
            chart: {
                fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
                toolbar: { show: false },
                zoom: { enabled: false },
                background: 'transparent',
                animations: { enabled: true, easing: 'easeinout', speed: 500 },
            },
            colors: palette.value,
            theme: { mode: isDark.value ? 'dark' : 'light' },
            grid: {
                borderColor: border,
                strokeDashArray: 3,
                xaxis: { lines: { show: false } },
                yaxis: { lines: { show: true } },
                padding: { top: 0, right: 10, bottom: 0, left: 10 },
            },
            dataLabels: { enabled: false },
            legend: { show: false },
            tooltip: { theme: isDark.value ? 'dark' : 'light' },
            xaxis: {
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: {
                    style: {
                        colors: muted,
                        fontSize: '11px',
                        fontWeight: 500,
                    },
                },
            },
            yaxis: {
                labels: {
                    style: { colors: muted, fontSize: '11px' },
                },
            },
        };
    });

    /** Called by the chart components when the theme flips. */
    function refresh() {
        revision.value += 1;
    }

    return { base, palette, isDark, refresh, resolvedAppearance };
}
