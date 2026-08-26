<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CloudUploadIcon, StarIcon, Trash2Icon } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

/**
 * Image picker for a product that does not exist yet.
 *
 * Nothing is uploaded here: the files ride along with the create request and
 * are stored once the product has an id. The already-saved counterpart is
 * ProductImageManager, which talks to the image endpoints directly.
 */
const files = defineModel<File[]>({ default: () => [] });

const page = usePage();
const input = ref<HTMLInputElement | null>(null);
const dragging = ref(false);
const rejected = ref<string[]>([]);

/**
 * The same limits the request validates against, so the form cannot disagree
 * with the backend about what is acceptable.
 */
const limits = computed(() => {
    const shared = (page.props.fileLimits ?? {}) as {
        mimes?: string[];
        maxKilobytes?: number;
        maxPerProduct?: number;
    };

    return {
        mimes: shared.mimes ?? ['jpg', 'jpeg', 'png', 'webp'],
        maxKilobytes: shared.maxKilobytes ?? 5120,
        maxPerProduct: shared.maxPerProduct ?? 12,
    };
});

const accept = computed(() =>
    limits.value.mimes.map((extension) => `.${extension}`).join(','),
);

/** "JPEG, PNG, up to 5 MB." — extensions as the user knows them. */
const hint = computed(() => {
    const names = limits.value.mimes
        .filter((extension) => extension !== 'jpeg')
        .map((extension) => extension.toUpperCase());
    const megabytes = limits.value.maxKilobytes / 1024;

    return `${names.join(', ')}, up to ${Number.isInteger(megabytes) ? megabytes : megabytes.toFixed(1)} MB.`;
});

/**
 * Object URLs are revoked when the list changes, so dropping twenty files and
 * removing them again does not leak twenty blobs.
 */
const previews = ref(new Map<File, string>());

watch(
    files,
    (current) => {
        const kept = new Set(current ?? []);

        previews.value.forEach((url, file) => {
            if (!kept.has(file)) {
                URL.revokeObjectURL(url);
                previews.value.delete(file);
            }
        });

        (current ?? []).forEach((file) => {
            if (!previews.value.has(file)) {
                previews.value.set(file, URL.createObjectURL(file));
            }
        });
    },
    { immediate: true, deep: true },
);

onBeforeUnmount(() => {
    previews.value.forEach((url) => URL.revokeObjectURL(url));
    previews.value.clear();
});

function pick() {
    input.value?.click();
}

function onSelected(event: Event) {
    const element = event.target as HTMLInputElement;

    accepting(Array.from(element.files ?? []));
    element.value = '';
}

function onDrop(event: DragEvent) {
    dragging.value = false;
    accepting(Array.from(event.dataTransfer?.files ?? []));
}

/**
 * Filter client-side for the same reasons the server does, and say which file
 * was refused and why — a silently dropped file reads as a broken control.
 */
function accepting(incoming: File[]) {
    const reasons: string[] = [];
    const room = limits.value.maxPerProduct - files.value.length;
    const kept: File[] = [];

    for (const file of incoming) {
        const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

        if (!limits.value.mimes.includes(extension)) {
            reasons.push(`${file.name} is not an accepted image type.`);
            continue;
        }

        if (file.size > limits.value.maxKilobytes * 1024) {
            reasons.push(`${file.name} is larger than ${limits.value.maxKilobytes / 1024} MB.`);
            continue;
        }

        if (kept.length >= room) {
            reasons.push(`${file.name} exceeds the limit of ${limits.value.maxPerProduct} images.`);
            continue;
        }

        kept.push(file);
    }

    rejected.value = reasons;
    files.value = [...files.value, ...kept];
}

function remove(index: number) {
    files.value = files.value.filter((_, position) => position !== index);
}

/** Promote by moving to the front: the first image becomes the primary. */
function makePrimary(index: number) {
    const next = [...files.value];

    next.unshift(...next.splice(index, 1));
    files.value = next;
}

function readableSize(bytes: number): string {
    return bytes >= 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}
</script>

<template>
    <div class="space-y-4">
        <div
            class="flex cursor-pointer flex-col items-stretch rounded-md border border-dashed transition-colors"
            :class="
                dragging
                    ? 'border-primary bg-accent/60'
                    : 'border-input bg-muted/40 hover:bg-muted/60'
            "
            @click="pick"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <div class="grow p-5 text-center">
                <div
                    class="mx-auto mb-3 flex size-8 items-center justify-center rounded-full border border-border bg-background"
                >
                    <CloudUploadIcon class="size-4 text-muted-foreground" />
                </div>

                <h3 class="mb-0.5 text-xs font-semibold text-foreground">
                    Choose a file or drag &amp; drop here.
                </h3>
                <span class="mb-3 block text-2xs text-muted-foreground">
                    {{ hint }}
                </span>

                <Button type="button" size="dense" @click.stop="pick">
                    Browse File
                </Button>
            </div>

            <input
                ref="input"
                type="file"
                class="hidden"
                :accept="accept"
                multiple
                @change="onSelected"
            />
        </div>

        <p
            v-for="reason in rejected"
            :key="reason"
            class="text-2xs text-danger"
        >
            {{ reason }}
        </p>

        <ul v-if="files.length" class="space-y-2">
            <li
                v-for="(file, index) in files"
                :key="`${file.name}-${file.size}-${index}`"
                class="flex items-center gap-3 rounded-md border border-border p-2"
            >
                <div
                    class="flex h-11 w-14 shrink-0 items-center justify-center overflow-hidden rounded-md border border-border bg-muted"
                >
                    <img
                        :src="previews.get(file)"
                        :alt="file.name"
                        class="size-full object-cover"
                    />
                </div>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-2sm font-medium">{{ file.name }}</p>
                    <p class="text-2xs text-muted-foreground">
                        {{ readableSize(file.size) }}
                    </p>
                </div>

                <Badge v-if="index === 0" variant="info" size="sm">Primary</Badge>

                <div class="flex shrink-0 items-center gap-1">
                    <Button
                        v-if="index > 0"
                        type="button"
                        variant="ghost"
                        size="icon-dense"
                        aria-label="Make primary"
                        @click="makePrimary(index)"
                    >
                        <StarIcon />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-dense"
                        aria-label="Remove image"
                        @click="remove(index)"
                    >
                        <Trash2Icon />
                    </Button>
                </div>
            </li>
        </ul>
    </div>
</template>
