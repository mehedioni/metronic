<script setup lang="ts">
defineProps<{ label?: string; showLabel?: boolean }>();
</script>

<template>
    <span class="flex items-center gap-2.5">
        <span class="flex size-7 items-center justify-center">
            <!-- The mark is drawn inline so it inherits no external asset and
                 stays crisp at any size. -->
            <svg
                class="size-6"
                viewBox="0 0 24 24"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
                aria-hidden="true"
            >
                <path
                    d="M4 4H10.5C14.0899 4 17 6.91015 17 10.5C17 14.0899 14.0899 17 10.5 17H4V4Z"
                    fill="#F43F5E"
                    fill-opacity="0.2"
                />
                <path
                    d="M3 3H9.5C13.6421 3 17 6.35786 17 10.5C17 14.6421 13.6421 18 9.5 18H3V3Z"
                    fill="#E11D48"
                />
                <path
                    d="M8 7H13.5C15.433 7 17 8.567 17 10.5C17 12.433 15.433 14 13.5 14H8V7Z"
                    fill="#BE123C"
                />
                <circle cx="17.5" cy="6.5" r="3.5" fill="#E11D48" />
            </svg>
        </span>

        <span
            v-if="showLabel"
            class="truncate text-sm font-bold tracking-tight text-zinc-900 dark:text-white"
        >
            {{ label }}
        </span>
    </span>
</template>
