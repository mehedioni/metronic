<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDownIcon } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { usePermissions } from '@/composables/usePermissions';
import { useSettingsDrawer } from '@/composables/useSettingsDrawer';
import { useSidebar } from '@/composables/useSidebar';
import AppLogo from '@/layouts/partials/AppLogo.vue';
import { navigation } from '@/lib/navigation';
import type { NavGroup, NavLink } from '@/lib/navigation';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';

const page = usePage<SharedData>();
const { can } = usePermissions();
const { collapsed, mobileOpen, closeMobile } = useSidebar();
const { openSettings } = useSettingsDrawer();

/** Groups keep only the links the signed-in user may actually follow. */
const sections = computed(() =>
    navigation
        .map((section) => ({
            ...section,
            groups: section.groups
                .map((group) => ({
                    ...group,
                    links: group.links.filter(
                        (link) => !link.permission || can(link.permission),
                    ),
                }))
                .filter((group) => group.links.length > 0),
        }))
        .filter((section) => section.groups.length > 0),
);

const currentPath = computed(() => page.url.split('?')[0]);

/** A drawer link opens over the current page, so it is never "current". */
function isCurrent(link: NavLink): boolean {
    if (!link.href) {
        return false;
    }

    const pageUrlObj = new URL(page.url, 'http://localhost');
    const linkUrlObj = new URL(link.href!, 'http://localhost');

    if (pageUrlObj.pathname !== linkUrlObj.pathname) {
        return false;
    }

    const linkParams = new URLSearchParams(linkUrlObj.search);
    const linkParamEntries = [...linkParams.entries()];

    if (linkParamEntries.length > 0) {
        const pageParams = new URLSearchParams(pageUrlObj.search);
        return linkParamEntries.every(
            ([key, val]) => pageParams.get(key) === val,
        );
    }

    const pageParams = new URLSearchParams(pageUrlObj.search);
    // If page has flow/filter params that belong to another specific link in sidebar, generic link shouldn't be active
    return pageParams.get('direction_flow') === null;
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

/** Links that navigate, and links that open the settings drawer. */
function pageLinks(group: NavGroup): NavLink[] {
    return group.links.filter((link) => link.href);
}

function drawerLinks(group: NavGroup): NavLink[] {
    return group.links.filter((link) => link.settingsTab);
}

function onDrawerLink(link: NavLink) {
    openSettings(link.settingsTab);
    closeMobile();
}

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
                'fixed inset-y-0 start-0 z-50 flex flex-col border-e border-dashed border-zinc-200/80 bg-white transition-[width,transform] duration-300 ease-out dark:border-zinc-800/80 dark:bg-[#121215]',
                collapsed ? 'w-18' : 'w-64',
                mobileOpen ? 'translate-x-0' : '-translate-x-full',
                'lg:translate-x-0',
            )
        "
    >
        <div
            class="flex h-16 shrink-0 items-center justify-between border-b border-dashed border-zinc-200/80 px-6 dark:border-zinc-800/80"
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
                    class="px-3 pb-2 text-2xs font-bold tracking-wider text-zinc-400 uppercase dark:text-zinc-500"
                >
                    {{ section.heading }}
                </p>

                <div class="space-y-3">
                    <div v-for="group in section.groups" :key="group.id">
                        <!-- Collapsed rail: opens the drawer, or follows the link -->
                        <button
                            v-if="collapsed && group.links[0].settingsTab"
                            type="button"
                            :title="group.label"
                            class="flex w-full cursor-pointer items-center justify-center rounded-lg px-3 py-2 text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white"
                            @click="onDrawerLink(group.links[0])"
                        >
                            <component :is="group.icon" class="size-4" />
                        </button>

                        <Link
                            v-else-if="collapsed"
                            :href="group.links[0].href!"
                            :title="group.label"
                            :class="
                                cn(
                                    'flex items-center justify-center rounded-lg px-3 py-2 transition-colors',
                                    isGroupActive(group)
                                        ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white'
                                        : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white',
                                )
                            "
                            @click="closeMobile"
                        >
                            <component :is="group.icon" class="size-4" />
                        </Link>

                        <template v-else>
                            <button
                                type="button"
                                :class="
                                    cn(
                                        'group flex w-full cursor-pointer items-center justify-between rounded-lg px-3 py-2 text-2sm transition-colors hover:text-blue-600 dark:hover:text-blue-500',
                                        isGroupActive(group)
                                            ? 'bg-zinc-50 font-semibold text-zinc-900 dark:bg-zinc-800/50 dark:text-white'
                                            : 'font-medium text-zinc-900 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-800',
                                    )
                                "
                                :aria-expanded="open[group.id]"
                                @click="toggleGroup(group.id)"
                            >
                                <span class="flex items-center gap-2.5">
                                    <component
                                        :is="group.icon"
                                        class="size-4 transition-colors group-hover:text-blue-600 dark:group-hover:text-blue-500"
                                        :class="
                                            isGroupActive(group)
                                                ? 'text-zinc-900 dark:text-white'
                                                : 'text-zinc-900 dark:text-zinc-300'
                                        "
                                    />
                                    <span
                                        :class="
                                            isGroupActive(group)
                                                ? 'text-2sm font-semibold'
                                                : 'text-2sm font-medium'
                                        "
                                    >
                                        {{ group.label }}
                                    </span>
                                </span>

                                <ChevronDownIcon
                                    class="size-3.5 text-zinc-400 transition-colors transition-transform group-hover:text-blue-600 dark:group-hover:text-blue-500"
                                    :class="open[group.id] ? 'rotate-180' : ''"
                                />
                            </button>

                            <div
                                v-show="open[group.id]"
                                class="mt-1 space-y-1 ps-7"
                            >
                                <button
                                    v-for="link in drawerLinks(group)"
                                    :key="link.label"
                                    type="button"
                                    class="block w-full cursor-pointer rounded-md px-3 py-1.5 text-start text-2sm text-zinc-900 transition-colors hover:bg-zinc-50 hover:text-blue-600 dark:text-zinc-400 dark:hover:bg-zinc-800/50 dark:hover:text-blue-500"
                                    @click="onDrawerLink(link)"
                                >
                                    {{ link.label }}
                                </button>

                                <Link
                                    v-for="link in pageLinks(group)"
                                    :key="link.href"
                                    :href="link.href!"
                                    :class="
                                        cn(
                                            'block rounded-md px-3 py-1.5 text-2sm transition-colors',
                                            isCurrent(link)
                                                ? 'bg-blue-50 font-medium text-blue-600 dark:bg-blue-500/10 dark:text-blue-500'
                                                : 'text-zinc-900 hover:bg-zinc-50 hover:text-blue-600 dark:text-zinc-400 dark:hover:bg-zinc-800/50 dark:hover:text-blue-500',
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
