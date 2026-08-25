import type { VariantProps } from 'class-variance-authority';
import { cva } from 'class-variance-authority';

export { default as Badge } from './Badge.vue';

/**
 * Tinted pill used for statuses and deltas. Every variant pairs a soft
 * background with the solid colour as text, which is what keeps them legible
 * in both themes without a second set of classes.
 */
export const badgeVariants = cva(
    'inline-flex items-center gap-1 whitespace-nowrap rounded-full border font-medium [&_svg]:size-3 [&_svg]:shrink-0',
    {
        variants: {
            variant: {
                neutral:
                    'border-border bg-muted text-muted-foreground',
                success:
                    'border-success/20 bg-success-soft text-success',
                warning:
                    'border-warning/20 bg-warning-soft text-warning',
                danger: 'border-danger/20 bg-danger-soft text-danger',
                info: 'border-info/20 bg-info-soft text-info',
                outline: 'border-border bg-transparent text-foreground',
                solid: 'border-transparent bg-primary text-primary-foreground',
            },
            size: {
                sm: 'px-1.5 py-0.5 text-2xs',
                default: 'px-2 py-0.5 text-xs',
                lg: 'px-2.5 py-1 text-xs',
            },
        },
        defaultVariants: {
            variant: 'neutral',
            size: 'default',
        },
    },
);

export type BadgeVariants = VariantProps<typeof badgeVariants>;
