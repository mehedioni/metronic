<script setup lang="ts">
defineProps<{
    label?: string;
    /** Validation message for this field, straight from useForm().errors. */
    error?: string;
    hint?: string;
    required?: boolean;
}>();
</script>

<template>
    <div class="flex flex-col gap-2">
        <label v-if="label" class="text-xs font-medium text-foreground">
            {{ label }}
            <span v-if="required" class="text-danger">*</span>
        </label>

        <slot />

        <p v-if="error" class="text-[11px] text-danger">{{ error }}</p>
        <p v-else-if="hint" class="text-[11px] text-muted-foreground">
            {{ hint }}
        </p>
    </div>
</template>
