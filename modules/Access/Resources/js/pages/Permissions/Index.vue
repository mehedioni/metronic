<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { SearchIcon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { humanize } from '@/lib/status';
import roleRoutes from '@/routes/access/roles';

interface AssignedPermission {
    id: number;
    name: string;
    roles: Array<{ id: number; name: string }>;
}

const props = defineProps<{
    groups: Record<string, string[]>;
    assigned: AssignedPermission[];
}>();

const search = ref('');

/** Which roles hold a given permission, keyed by permission name. */
const rolesByPermission = computed(() =>
    Object.fromEntries(
        props.assigned.map((permission) => [permission.name, permission.roles]),
    ),
);

/**
 * Groups filtered by the search term, dropping any group left with no
 * matching permission. Filtering is local because the whole catalogue is
 * already on the page — it is a fixed list, not a paginated query.
 */
const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();

    return Object.entries(props.groups)
        .map(([group, permissions]) => [
            group,
            term
                ? permissions.filter(
                      (name) =>
                          name.toLowerCase().includes(term) ||
                          group.toLowerCase().includes(term),
                  )
                : permissions,
        ] as [string, string[]])
        .filter(([, permissions]) => permissions.length > 0);
});

const total = computed(() => Object.values(props.groups).flat().length);

const breadcrumbs = [
    { label: 'Administration' },
    { label: 'Access' },
    { label: 'Permissions' },
];
</script>

<template>
    <Head title="Permissions" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageHeader
            title="Permissions"
            :description="`${total} permissions across ${Object.keys(props.groups).length} groups. This is the catalogue the backend enforces — it is defined in code, not editable here.`"
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <Input
                    v-model="search"
                    placeholder="Search permission"
                    has-icon
                    class="w-64"
                >
                    <template #icon><SearchIcon /></template>
                </Input>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card v-for="[group, permissions] in filtered" :key="group">
                <CardHeader>
                    <template #title>
                        <CardTitle
                            :description="`${permissions.length} permissions`"
                            >{{ humanize(group) }}</CardTitle
                        >
                    </template>
                </CardHeader>

                <ul class="divide-y divide-border">
                    <li
                        v-for="permission in permissions"
                        :key="permission"
                        class="flex flex-wrap items-center justify-between gap-2 px-5 py-2.5"
                    >
                        <code class="font-mono text-2xs">{{ permission }}</code>

                        <span class="flex flex-wrap gap-1">
                            <Link
                                v-for="role in rolesByPermission[permission] ?? []"
                                :key="role.id"
                                :href="roleRoutes.show.url(role.id)"
                            >
                                <Badge variant="outline" size="sm">{{
                                    role.name
                                }}</Badge>
                            </Link>
                            <span
                                v-if="!(rolesByPermission[permission] ?? []).length"
                                class="text-2xs text-muted-foreground"
                                >No role</span
                            >
                        </span>
                    </li>
                </ul>
            </Card>
        </div>

        <p
            v-if="!filtered.length"
            class="py-10 text-center text-sm text-muted-foreground"
        >
            No permission matches “{{ search }}”.
        </p>
    </AppLayout>
</template>
