<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDownIcon } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { usePermissions } from '@/composables/usePermissions';
import { useSidebar } from '@/composables/useSidebar';
import AppLogo from '@/layouts/partials/AppLogo.vue';
import { navigation } from '@/lib/navigation';
import type { NavGroup, NavLink } from '@/lib/navigation';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';

const page = usePage<SharedData>();
const { can } = usePermissions();
const { collapsed, mobileOpen, closeMobile } = useSidebar();

/** Groups keep only the links the signed-in user may actually follow. */
const sections = computed(() =>
    navigation
        .map((section) => ({
            ...section,
            groups: section.groups
                .map((group) => ({
                    ...group,
                    links: group.links.filter((link) => can(link.permission)),
                }))
                .filter((group) => group.links.length > 0),
        }))
        .filter((section) => section.groups.length > 0),
);

const currentPath = computed(() => page.url.split('?')[0]);

function isCurrent(link: NavLink): boolean {
    return currentPath.value === link.href.split('?')[0];
}

function isGroupActive(group: NavGroup): boolean {
    return group.links.some((link) => isCurrent(link));
}

/** Sections holding the current page start open, the rest stay shut. */
const open = ref<Record<string, boolean>>({});

watch(
    [sections, currentPath],
    () => {
        sections.value.forEach((section) =>
            section.groups.forEach((group) => {
                if (isGroupActive(group)) {
                    open.value[group.id] = true;
                } else if (open.value[group.id] === undefined) {
                    open.value[group.id] = false;
                }
            }),
        );
    },
    { immediate: true },
);

function toggleGroup(id: string) {
    open.value[id] = !open.value[id];
}
</script>

<template>
    <!-- Mobile backdrop -->
    <div
        v-if="mobileOpen"
        class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm lg:hidden"
        @click="closeMobile"
    />

    <aside
        :class="
            cn(
                'fixed inset-y-0 start-0 z-50 flex flex-col border-e border-sidebar-border bg-sidebar transition-[width,transform] duration-300 ease-out',
                collapsed ? 'w-18' : 'w-64',
                mobileOpen ? 'translate-x-0' : '-translate-x-full',
                'lg:translate-x-0',
            )
        "
    >
        <div
            class="flex h-16 shrink-0 items-center justify-between border-b border-dashed border-sidebar-border px-5"
        >
            <Link href="/dashboard" class="min-w-0" @click="closeMobile">
                <AppLogo
                    :label="page.props.app.name"
                    :show-label="!collapsed"
                />
            </Link>
        </div>

        <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
            <div
                v-for="(section, index) in sections"
                :key="section.heading ?? index"
            >
                <p
                    v-if="section.heading && !collapsed"
                    class="px-3 pb-2 text-[11px] font-bold tracking-wider text-muted-foreground uppercase"
                >
                    {{ section.heading }}
                </p>

                <div class="space-y-1">
                    <div v-for="group in section.groups" :key="group.id">
                        <!-- A collapsed rail has no room for the accordion, so
                             the group icon links straight to its first page. -->
                        <Link
                            v-if="collapsed"
                            :href="group.links[0].href"
                            :title="group.label"
                            :class="
                                cn(
                                    'flex items-center justify-center rounded-lg px-3 py-2 transition-colors',
                                    isGroupActive(group)
                                        ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                                        : 'text-sidebar-foreground hover:bg-sidebar-accent',
                                )
                            "
                            @click="closeMobile"
                        >
                            <component :is="group.icon" class="size-4" />
                        </Link>

                        <template v-else>
                            <button
                                type="button"
                                class="flex w-full cursor-pointer items-center justify-between rounded-lg px-3 py-2 text-sm font-medium text-sidebar-foreground transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                                :aria-expanded="open[group.id]"
                                @click="toggleGroup(group.id)"
                            >
                                <span class="flex items-center gap-2.5">
                                    <component
                                        :is="group.icon"
                                        class="size-4 text-muted-foreground"
                                    />
                                    <span>{{ group.label }}</span>
                                </span>

                                <ChevronDownIcon
                                    class="size-3.5 text-muted-foreground transition-transform"
                                    :class="open[group.id] ? 'rotate-180' : ''"
                                />
                            </button>

                            <div
                                v-show="open[group.id]"
                                class="mt-1 space-y-1 ps-7"
                            >
                                <Link
                                    v-for="link in group.links"
                                    :key="link.href"
                                    :href="link.href"
                                    :class="
                                        cn(
                                            'block rounded-md px-3 py-1.5 text-xs transition-colors',
                                            isCurrent(link)
                                                ? 'bg-sidebar-accent font-medium text-info'
                                                : 'text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                                        )
                                    "
                                    @click="closeMobile"
                                >
                                    {{ link.label }}
                                </Link>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </nav>
    </aside>
</template>
