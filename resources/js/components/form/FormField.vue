<script setup lang="ts">
defineProps<{
    label?: string;
    /** Validation message for this field, straight from useForm().errors. */
    error?: string;
    hint?: string;
    required?: boolean;
    /**
     * Label beside the control instead of above it. Suits a short list of
     * contact details, where a column of labels is easier to scan than
     * stacked pairs.
     */
    horizontal?: boolean;
}>();
</script>

<template>
    <div
        :class="
            horizontal
                ? 'grid items-baseline gap-1.5 sm:grid-cols-[140px_minmax(0,1fr)] sm:gap-4'
                : 'flex flex-col gap-2'
        "
    >
        <label
            v-if="label"
            class="text-xs font-medium text-foreground"
            :class="horizontal && 'sm:pt-2'"
        >
            {{ label }}
            <span v-if="required" class="text-danger">*</span>
        </label>

        <div :class="horizontal && 'min-w-0'">
            <slot />

            <p v-if="error" class="mt-1.5 text-2xs text-danger">{{ error }}</p>
            <p v-else-if="hint" class="mt-1.5 text-2xs text-muted-foreground">
                {{ hint }}
            </p>
        </div>
    </div>
</template>
