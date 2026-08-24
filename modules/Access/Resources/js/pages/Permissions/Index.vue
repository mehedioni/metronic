<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DataCard from '@/components/DataCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';

defineProps<{
    groups: Record<string, string[]>;
    assigned: Array<{
        id: number;
        name: string;
        roles: Array<{ id: number; name: string }>;
    }>;
}>();
</script>

<template>
    <Head title="Permissions" />

    <AppLayout title="Permissions">
        <DataCard
            title="Permission catalogue"
            description="Permissions are declared in App\Core\Support\Permissions and seeded — they are not created here."
        >
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-2">Permission</th>
                        <th class="px-4 py-2">Granted to</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="permission in assigned"
                        :key="permission.id"
                        class="border-t border-border"
                    >
                        <td class="px-4 py-2">{{ permission.name }}</td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{
                                permission.roles.map((role) => role.name).join(', ') ||
                                '—'
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </DataCard>
    </AppLayout>
</template>
