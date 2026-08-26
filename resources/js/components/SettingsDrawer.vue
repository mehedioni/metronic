<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { ImageIcon } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import SettingsRow from '@/components/SettingsRow.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Drawer } from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { TabPanel, Tabs } from '@/components/ui/tabs';
import { usePermissions } from '@/composables/usePermissions';
import { useSettingsDrawer } from '@/composables/useSettingsDrawer';
import type { SettingsTab } from '@/composables/useSettingsDrawer';
import type { SharedData } from '@/types';

/**
 * Settings, as a sheet over whichever page the user is on.
 *
 * Two tabs, two audiences: General is the store, and needs the
 * settings.manage permission; Profile is the signed-in user's own account and
 * needs none. The tab strip therefore only offers General to someone who can
 * actually save it — and the route enforces that regardless.
 */
const page = usePage<SharedData>();
const { can } = usePermissions();
const { open, tab, closeSettings } = useSettingsDrawer();

const canManage = computed(() => can('settings.manage'));

const tabs = computed(() =>
    [
        canManage.value ? { value: 'general', label: 'General' } : null,
        { value: 'profile', label: 'Profile' },
    ].filter((entry): entry is { value: string; label: string } => entry !== null),
);

/** Someone without the store permission only has one tab to be on. */
watch([open, canManage], () => {
    if (open.value && !canManage.value) {
        tab.value = 'profile';
    }
});

const logoInput = ref<HTMLInputElement | null>(null);
const logoPreview = ref<string | null>(null);

const general = useForm<{
    company_name: string;
    currency: string;
    logo: File | null;
    remove_logo: boolean;
}>({
    company_name: page.props.settings.companyName,
    currency: page.props.settings.currency.code,
    logo: null,
    remove_logo: false,
});

const profile = useForm({ name: page.props.auth.user?.name ?? '' });

const password = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

/**
 * Re-seed from the shared props whenever the drawer opens, so a form left
 * half-edited and dismissed does not reappear that way.
 */
watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    general.defaults({
        company_name: page.props.settings.companyName,
        currency: page.props.settings.currency.code,
        logo: null,
        remove_logo: false,
    });
    general.reset();
    general.clearErrors();

    profile.defaults({ name: page.props.auth.user?.name ?? '' });
    profile.reset();
    profile.clearErrors();

    password.reset();
    password.clearErrors();

    revokePreview();
});

/** What the logo tile shows: a newly chosen file, else the stored one. */
const shownLogo = computed(
    () => logoPreview.value ?? (general.remove_logo ? null : page.props.settings.logoUrl),
);

function revokePreview() {
    if (logoPreview.value) {
        URL.revokeObjectURL(logoPreview.value);
        logoPreview.value = null;
    }
}

function onLogoSelected(event: Event) {
    const element = event.target as HTMLInputElement;
    const chosen = element.files?.[0] ?? null;

    element.value = '';

    if (!chosen) {
        return;
    }

    revokePreview();
    logoPreview.value = URL.createObjectURL(chosen);
    general.logo = chosen;
    general.remove_logo = false;
}

function removeLogo() {
    revokePreview();
    general.logo = null;
    general.remove_logo = true;
}

function saveGeneral() {
    general.put('/settings/general', {
        preserveScroll: true,
        onSuccess: () => {
            revokePreview();
            general.logo = null;
            general.remove_logo = false;
        },
    });
}

function saveProfile() {
    profile.put('/settings/profile', { preserveScroll: true });
}

function savePassword() {
    password.put('/password', {
        preserveScroll: true,
        onSuccess: () => password.reset(),
        onError: () => password.reset('password', 'password_confirmation'),
    });
}

/** The tab strip's save button acts on whichever tab is showing. */
function save() {
    if (tab.value === 'general') {
        saveGeneral();

        return;
    }

    saveProfile();
}

const saving = computed(() =>
    tab.value === 'general' ? general.processing : profile.processing,
);
</script>

<template>
    <Drawer
        :open="open"
        title="Settings"
        size="md"
        flush
        @update:open="!$event && closeSettings()"
    >
        <div class="flex min-h-full flex-col">
            <!--
                Hero bar: what is being configured, and the actions that apply
                to it. Mirrors the reference, minus the figures we do not keep
                (store id, established date).
            -->
            <div
                class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4"
            >
                <div class="flex min-w-0 flex-col gap-2">
                    <div class="flex items-center gap-2.5">
                        <span class="truncate text-lg leading-none font-semibold">
                            {{ page.props.settings.companyName }}
                        </span>
                        <Badge variant="success" size="sm">Live</Badge>
                    </div>
                    <p class="text-2xs text-muted-foreground">
                        Signed in as
                        <span class="font-medium text-foreground">
                            {{ page.props.auth.user?.email }}
                        </span>
                    </p>
                </div>

                <div class="flex items-center gap-2.5">
                    <Button variant="ghost" size="dense" @click="closeSettings">
                        Close
                    </Button>
                    <Button size="dense" :disabled="saving" @click="save">
                        Save
                    </Button>
                </div>
            </div>

            <Tabs
                :model-value="tab"
                :tabs="tabs"
                class="flex min-h-0 flex-1 flex-col"
                @update:model-value="tab = $event as SettingsTab"
            >
                <div class="flex-1 space-y-5 px-5 py-5">
                    <TabPanel v-if="canManage" value="general">
                        <Card class="p-5">
                            <div class="mb-1 flex flex-col gap-1">
                                <span class="text-2sm font-semibold">Basics</span>
                                <span class="text-2xs text-muted-foreground">
                                    How the store names and prices itself.
                                </span>
                            </div>

                            <SettingsRow
                                label="Company name"
                                description="Shown in the sidebar, the browser tab and on documents."
                                :error="general.errors.company_name"
                            >
                                <Input
                                    v-model="general.company_name"
                                    placeholder="Company name"
                                    :invalid="Boolean(general.errors.company_name)"
                                />
                            </SettingsRow>

                            <SettingsRow
                                label="Logo"
                                description="Store logo or brand icon."
                                :error="general.errors.logo"
                            >
                                <div class="flex flex-row items-center gap-4">
                                    <div
                                        class="flex size-15 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border bg-muted/40"
                                    >
                                        <img
                                            v-if="shownLogo"
                                            :src="shownLogo"
                                            alt="Store logo"
                                            class="size-full object-contain p-1"
                                        />
                                        <ImageIcon
                                            v-else
                                            class="size-5 text-muted-foreground/60"
                                        />
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="dense"
                                            @click="logoInput?.click()"
                                        >
                                            Upload
                                        </Button>
                                        <Button
                                            v-if="shownLogo"
                                            type="button"
                                            variant="ghost"
                                            size="dense"
                                            @click="removeLogo"
                                        >
                                            Delete
                                        </Button>
                                    </div>

                                    <input
                                        ref="logoInput"
                                        type="file"
                                        class="hidden"
                                        :accept="
                                            page.props.fileLimits.mimes
                                                .map((mime) => `.${mime}`)
                                                .join(',')
                                        "
                                        @change="onLogoSelected"
                                    />
                                </div>
                            </SettingsRow>

                            <SettingsRow
                                label="Currency"
                                description="Every amount in the application is written in this currency."
                                :error="general.errors.currency"
                                last
                            >
                                <Select v-model="general.currency">
                                    <option
                                        v-for="option in page.props.currencies"
                                        :key="option.code"
                                        :value="option.code"
                                    >
                                        {{ option.name }} ({{ option.symbol }})
                                    </option>
                                </Select>
                            </SettingsRow>
                        </Card>
                    </TabPanel>

                    <TabPanel value="profile">
                        <Card class="p-5">
                            <div class="mb-1 flex flex-col gap-1">
                                <span class="text-2sm font-semibold">Your account</span>
                                <span class="text-2xs text-muted-foreground">
                                    Yours alone — these are not store settings.
                                </span>
                            </div>

                            <SettingsRow
                                label="Name"
                                description="How you appear to everyone else in the store."
                                :error="profile.errors.name"
                            >
                                <Input
                                    v-model="profile.name"
                                    placeholder="Your name"
                                    :invalid="Boolean(profile.errors.name)"
                                />
                            </SettingsRow>

                            <SettingsRow
                                label="Email"
                                description="Identifies your account. An administrator changes it."
                                last
                            >
                                <Input
                                    :model-value="page.props.auth.user?.email ?? ''"
                                    readonly
                                    disabled
                                />
                            </SettingsRow>
                        </Card>

                        <Card class="mt-5 p-5">
                            <div class="mb-1 flex flex-col gap-1">
                                <span class="text-2sm font-semibold">Password</span>
                                <span class="text-2xs text-muted-foreground">
                                    Your current password is required, so a
                                    borrowed session cannot lock you out.
                                </span>
                            </div>

                            <SettingsRow
                                label="Current password"
                                :error="password.errors.current_password"
                            >
                                <Input
                                    v-model="password.current_password"
                                    type="password"
                                    autocomplete="current-password"
                                    :invalid="Boolean(password.errors.current_password)"
                                />
                            </SettingsRow>

                            <SettingsRow
                                label="New password"
                                :error="password.errors.password"
                            >
                                <Input
                                    v-model="password.password"
                                    type="password"
                                    autocomplete="new-password"
                                    :invalid="Boolean(password.errors.password)"
                                />
                            </SettingsRow>

                            <SettingsRow
                                label="Confirm new password"
                                :error="password.errors.password_confirmation"
                                last
                            >
                                <div class="flex items-center gap-2.5">
                                    <Input
                                        v-model="password.password_confirmation"
                                        type="password"
                                        autocomplete="new-password"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="dense"
                                        :disabled="password.processing"
                                        @click="savePassword"
                                    >
                                        Change
                                    </Button>
                                </div>
                            </SettingsRow>
                        </Card>
                    </TabPanel>
                </div>
            </Tabs>
        </div>

        <template #footer>
            <Button variant="ghost" size="dense" @click="closeSettings">
                Close
            </Button>
            <Button size="dense" :disabled="saving" @click="save">Save</Button>
        </template>
    </Drawer>
</template>
