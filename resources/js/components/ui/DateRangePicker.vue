<script setup lang="ts">
import {
    Calendar as CalendarIcon,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    X,
} from 'lucide-vue-next';
import {
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
} from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { computed, ref, watch } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        modelValue?: { start: Date | string | null; end: Date | string | null } | null;
        startDate?: Date | string | null;
        endDate?: Date | string | null;
        from?: Date | string | null;
        to?: Date | string | null;
        placeholder?: string;
        defaultLabel?: string;
        align?: 'start' | 'center' | 'end';
        clearable?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    {
        modelValue: undefined,
        startDate: undefined,
        endDate: undefined,
        from: undefined,
        to: undefined,
        placeholder: 'Filter by date',
        defaultLabel: undefined,
        align: 'start',
        clearable: true,
        class: undefined,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: { start: Date; end: Date } | null];
    'update:startDate': [value: string | null];
    'update:endDate': [value: string | null];
    'update:from': [value: string];
    'update:to': [value: string];
    change: [
        value: {
            start: Date | null;
            end: Date | null;
            from: string;
            to: string;
            label: string;
        },
    ];
    clear: [];
}>();

function parseDate(val: Date | string | null | undefined): Date | null {
    if (!val) return null;
    if (val instanceof Date) {
        return isNaN(val.getTime())
            ? null
            : new Date(val.getFullYear(), val.getMonth(), val.getDate());
    }
    if (typeof val === 'string' && val.trim() !== '') {
        const trimmed = val.trim();
        const parts = trimmed.split(/[-/T ]/);
        if (parts.length >= 3) {
            const year = parseInt(parts[0], 10);
            const month = parseInt(parts[1], 10) - 1;
            const day = parseInt(parts[2], 10);
            if (!isNaN(year) && !isNaN(month) && !isNaN(day)) {
                return new Date(year, month, day);
            }
        }
        const d = new Date(trimmed);
        return isNaN(d.getTime())
            ? null
            : new Date(d.getFullYear(), d.getMonth(), d.getDate());
    }
    return null;
}

function formatDateToIso(d: Date | null | undefined): string {
    if (!d || isNaN(d.getTime())) return '';
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function getInitialDates(): { start: Date | null; end: Date | null } {
    let start: Date | null = null;
    let end: Date | null = null;

    if (props.from !== undefined || props.to !== undefined) {
        start = parseDate(props.from);
        end = parseDate(props.to);
    } else if (props.startDate !== undefined || props.endDate !== undefined) {
        start = parseDate(props.startDate);
        end = parseDate(props.endDate);
    } else if (props.modelValue) {
        start = parseDate(props.modelValue.start);
        end = parseDate(props.modelValue.end);
    }

    return { start, end };
}

const isOpen = ref(false);
const activePreset = ref<string>('custom');

const initial = getInitialDates();
const currentStart = ref<Date | null>(initial.start);
const currentEnd = ref<Date | null>(initial.end);

const tempStart = ref<Date | null>(initial.start ? new Date(initial.start) : null);
const tempEnd = ref<Date | null>(initial.end ? new Date(initial.end) : null);

// Display month in left calendar
const viewDate = ref<Date>(
    initial.start
        ? new Date(initial.start.getFullYear(), initial.start.getMonth(), 1)
        : new Date(new Date().getFullYear(), new Date().getMonth(), 1),
);

watch(
    () => [props.from, props.to, props.startDate, props.endDate, props.modelValue],
    () => {
        const { start, end } = getInitialDates();
        currentStart.value = start;
        currentEnd.value = end;
        tempStart.value = start ? new Date(start) : null;
        tempEnd.value = end ? new Date(end) : null;
        if (start) {
            viewDate.value = new Date(start.getFullYear(), start.getMonth(), 1);
        }
    },
    { deep: true },
);

watch(isOpen, (open) => {
    if (open) {
        tempStart.value = currentStart.value ? new Date(currentStart.value) : null;
        tempEnd.value = currentEnd.value ? new Date(currentEnd.value) : null;
        if (currentStart.value) {
            viewDate.value = new Date(
                currentStart.value.getFullYear(),
                currentStart.value.getMonth(),
                1,
            );
        }
    }
});

const monthNames = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

const shortMonthNames = [
    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
];

const leftMonth = computed(() => ({
    year: viewDate.value.getFullYear(),
    month: viewDate.value.getMonth(),
    name: `${monthNames[viewDate.value.getMonth()]} ${viewDate.value.getFullYear()}`,
}));

const rightMonth = computed(() => {
    const next = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth() + 1, 1);
    return {
        year: next.getFullYear(),
        month: next.getMonth(),
        name: `${monthNames[next.getMonth()]} ${next.getFullYear()}`,
    };
});

function prevMonth() {
    viewDate.value = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth() - 1, 1);
}

function nextMonth() {
    viewDate.value = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth() + 1, 1);
}

function getDaysInMonth(year: number, month: number) {
    const firstDayIndex = new Date(year, month, 1).getDay();
    const daysInCurrent = new Date(year, month + 1, 0).getDate();
    const daysInPrev = new Date(year, month, 0).getDate();

    const days: Array<{
        date: Date;
        dayNum: number;
        isCurrentMonth: boolean;
    }> = [];

    // Prev month padding
    for (let i = firstDayIndex - 1; i >= 0; i--) {
        days.push({
            date: new Date(year, month - 1, daysInPrev - i),
            dayNum: daysInPrev - i,
            isCurrentMonth: false,
        });
    }

    // Current month days
    for (let i = 1; i <= daysInCurrent; i++) {
        days.push({
            date: new Date(year, month, i),
            dayNum: i,
            isCurrentMonth: true,
        });
    }

    // Next month padding to fill 42 cells (6 rows)
    const remaining = 42 - days.length;
    for (let i = 1; i <= remaining; i++) {
        days.push({
            date: new Date(year, month + 1, i),
            dayNum: i,
            isCurrentMonth: false,
        });
    }

    return days;
}

const leftDays = computed(() => getDaysInMonth(leftMonth.value.year, leftMonth.value.month));
const rightDays = computed(() => getDaysInMonth(rightMonth.value.year, rightMonth.value.month));

function isSameDay(d1: Date, d2: Date) {
    return (
        d1.getFullYear() === d2.getFullYear() &&
        d1.getMonth() === d2.getMonth() &&
        d1.getDate() === d2.getDate()
    );
}

function isDateInRange(date: Date, start: Date | null, end: Date | null) {
    if (!start || !end) return false;
    const time = new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
    const s = new Date(start.getFullYear(), start.getMonth(), start.getDate()).getTime();
    const e = new Date(end.getFullYear(), end.getMonth(), end.getDate()).getTime();
    return time >= s && time <= e;
}

function isStartDate(date: Date, start: Date | null) {
    if (!start) return false;
    return isSameDay(date, start);
}

function isEndDate(date: Date, end: Date | null) {
    if (!end) return false;
    return isSameDay(date, end);
}

function handleDateClick(date: Date) {
    activePreset.value = 'custom';
    if (!tempStart.value || (tempStart.value && tempEnd.value)) {
        tempStart.value = new Date(date);
        tempEnd.value = null;
    } else if (tempStart.value && !tempEnd.value) {
        if (date < tempStart.value) {
            tempEnd.value = new Date(tempStart.value);
            tempStart.value = new Date(date);
        } else {
            tempEnd.value = new Date(date);
        }
    }
}

const presets = [
    { key: 'today', label: 'Today' },
    { key: 'yesterday', label: 'Yesterday' },
    { key: 'last_7_days', label: 'Last 7 Days' },
    { key: 'last_30_days', label: 'Last 30 Days' },
    { key: 'this_month', label: 'This Month' },
    { key: 'last_month', label: 'Last Month' },
    { key: 'this_year', label: 'This Year' },
    { key: 'custom', label: 'Custom Range' },
];

function selectPreset(presetKey: string) {
    activePreset.value = presetKey;
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());

    if (presetKey === 'today') {
        tempStart.value = new Date(today);
        tempEnd.value = new Date(today);
    } else if (presetKey === 'yesterday') {
        const y = new Date(today);
        y.setDate(y.getDate() - 1);
        tempStart.value = y;
        tempEnd.value = y;
    } else if (presetKey === 'last_7_days') {
        const s = new Date(today);
        s.setDate(s.getDate() - 6);
        tempStart.value = s;
        tempEnd.value = new Date(today);
    } else if (presetKey === 'last_30_days') {
        const s = new Date(today);
        s.setDate(s.getDate() - 29);
        tempStart.value = s;
        tempEnd.value = new Date(today);
    } else if (presetKey === 'this_month') {
        tempStart.value = new Date(today.getFullYear(), today.getMonth(), 1);
        tempEnd.value = new Date(today);
    } else if (presetKey === 'last_month') {
        tempStart.value = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        tempEnd.value = new Date(today.getFullYear(), today.getMonth(), 0);
    } else if (presetKey === 'this_year') {
        tempStart.value = new Date(today.getFullYear(), 0, 1);
        tempEnd.value = new Date(today);
    }

    if (tempStart.value) {
        viewDate.value = new Date(tempStart.value.getFullYear(), tempStart.value.getMonth(), 1);
    }
}

function formatDate(d: Date) {
    return `${shortMonthNames[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()}`;
}

const displayLabel = computed(() => {
    if (currentStart.value && currentEnd.value) {
        if (isSameDay(currentStart.value, currentEnd.value)) {
            return formatDate(currentStart.value);
        }
        if (currentStart.value.getFullYear() === currentEnd.value.getFullYear()) {
            if (currentStart.value.getMonth() === currentEnd.value.getMonth()) {
                return `${shortMonthNames[currentStart.value.getMonth()]} ${currentStart.value.getDate()} - ${currentEnd.value.getDate()}, ${currentStart.value.getFullYear()}`;
            }
            return `${shortMonthNames[currentStart.value.getMonth()]} ${currentStart.value.getDate()} - ${shortMonthNames[currentEnd.value.getMonth()]} ${currentEnd.value.getDate()}, ${currentStart.value.getFullYear()}`;
        }
        return `${formatDate(currentStart.value)} - ${formatDate(currentEnd.value)}`;
    }
    if (currentStart.value) {
        return `From ${formatDate(currentStart.value)}`;
    }
    if (currentEnd.value) {
        return `Until ${formatDate(currentEnd.value)}`;
    }
    return props.defaultLabel ?? props.placeholder ?? 'Filter by date';
});

const isFiltered = computed(() => Boolean(currentStart.value || currentEnd.value));

function cancel() {
    tempStart.value = currentStart.value ? new Date(currentStart.value) : null;
    tempEnd.value = currentEnd.value ? new Date(currentEnd.value) : null;
    isOpen.value = false;
}

function apply() {
    if (tempStart.value) {
        currentStart.value = new Date(tempStart.value);
        currentEnd.value = tempEnd.value ? new Date(tempEnd.value) : new Date(tempStart.value);
    } else {
        currentStart.value = null;
        currentEnd.value = null;
    }

    const startIso = formatDateToIso(currentStart.value);
    const endIso = formatDateToIso(currentEnd.value);

    emit(
        'update:modelValue',
        currentStart.value && currentEnd.value
            ? { start: currentStart.value, end: currentEnd.value }
            : null,
    );
    emit('update:startDate', startIso || null);
    emit('update:endDate', endIso || null);
    emit('update:from', startIso);
    emit('update:to', endIso);
    emit('change', {
        start: currentStart.value,
        end: currentEnd.value,
        from: startIso,
        to: endIso,
        label: displayLabel.value,
    });
    isOpen.value = false;
}

function clearDates() {
    activePreset.value = 'custom';
    tempStart.value = null;
    tempEnd.value = null;
    currentStart.value = null;
    currentEnd.value = null;

    emit('update:modelValue', null);
    emit('update:startDate', null);
    emit('update:endDate', null);
    emit('update:from', '');
    emit('update:to', '');
    emit('change', {
        start: null,
        end: null,
        from: '',
        to: '',
        label: props.placeholder ?? props.defaultLabel ?? 'Filter by date',
    });
    emit('clear');
    isOpen.value = false;
}
</script>

<template>
    <PopoverRoot v-model:open="isOpen">
        <PopoverTrigger as-child>
            <button
                type="button"
                :class="
                    cn(
                        'inline-flex h-8.5 cursor-pointer items-center gap-2 rounded-md border bg-background px-3 text-2sm text-foreground shadow-xs transition-colors hover:bg-muted/50 focus:outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800',
                        isFiltered ? 'border-primary/50 text-foreground font-medium' : 'border-input text-muted-foreground',
                        props.class,
                    )
                "
            >
                <CalendarIcon class="size-3.5 text-muted-foreground shrink-0" />
                <span class="truncate max-w-[220px]">{{ displayLabel }}</span>
                <span
                    v-if="clearable && isFiltered"
                    role="button"
                    title="Clear date filter"
                    class="ms-auto -me-1 inline-flex size-4 shrink-0 cursor-pointer items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
                    @click.stop="clearDates"
                >
                    <X class="size-3" />
                </span>
                <ChevronDown
                    v-else
                    class="size-3.5 shrink-0 text-muted-foreground transition-transform duration-200"
                    :class="isOpen ? 'rotate-180' : ''"
                />
            </button>
        </PopoverTrigger>

        <PopoverPortal>
            <PopoverContent
                :align="align"
                :side-offset="6"
                class="z-50 flex flex-col overflow-hidden rounded-lg border border-border bg-popover text-popover-foreground shadow-xl dark:border-zinc-800 dark:bg-[#121215] sm:flex-row"
            >
                <!-- Presets Sidebar -->
                <div class="w-full border-b border-border p-3 dark:border-zinc-800 sm:w-44 sm:border-b-0 sm:border-e">
                    <div class="space-y-1">
                        <button
                            v-for="preset in presets"
                            :key="preset.key"
                            type="button"
                            class="flex w-full cursor-pointer items-center justify-between rounded-md px-2.5 py-1.5 text-left text-xs font-medium transition-colors"
                            :class="
                                activePreset === preset.key
                                    ? 'bg-primary/10 text-primary dark:bg-primary/20 dark:text-primary-foreground font-semibold'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                            "
                            @click="selectPreset(preset.key)"
                        >
                            <span>{{ preset.label }}</span>
                        </button>
                    </div>
                </div>

                <!-- Calendars & Action Footer Container -->
                <div class="flex flex-col">
                    <!-- Calendars Header & Grid -->
                    <div class="flex flex-col gap-6 p-4 md:flex-row">
                        <!-- Left Month Calendar -->
                        <div class="w-64">
                            <div class="mb-3 flex items-center justify-between">
                                <button
                                    type="button"
                                    class="cursor-pointer rounded p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                    @click="prevMonth"
                                >
                                    <ChevronLeft class="size-4" />
                                </button>
                                <span class="text-xs font-semibold text-foreground">
                                    {{ leftMonth.name }}
                                </span>
                                <span class="w-6"></span>
                            </div>

                            <!-- Weekday Names -->
                            <div class="mb-1 grid grid-cols-7 text-center text-2xs font-medium text-muted-foreground">
                                <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
                            </div>

                            <!-- Days Grid -->
                            <div class="grid grid-cols-7 gap-y-1 text-center text-xs">
                                <div
                                    v-for="(day, idx) in leftDays"
                                    :key="idx"
                                    class="relative py-0.5"
                                >
                                    <!-- Range Highlight Background -->
                                    <div
                                        v-if="isDateInRange(day.date, tempStart, tempEnd)"
                                        class="absolute inset-y-0 bg-primary/10 dark:bg-primary/20"
                                        :class="{
                                            'rounded-s-md left-0': isStartDate(day.date, tempStart),
                                            'rounded-e-md right-0': isEndDate(day.date, tempEnd),
                                            'left-0 right-0': !isStartDate(day.date, tempStart) && !isEndDate(day.date, tempEnd),
                                        }"
                                    />

                                    <button
                                        type="button"
                                        class="relative z-10 mx-auto flex size-7 cursor-pointer items-center justify-center rounded-md text-2xs font-medium transition-colors"
                                        :class="[
                                            !day.isCurrentMonth ? 'text-muted-foreground/40' : 'text-foreground',
                                            isStartDate(day.date, tempStart) || isEndDate(day.date, tempEnd)
                                                ? 'bg-primary text-primary-foreground font-semibold shadow-xs'
                                                : isDateInRange(day.date, tempStart, tempEnd)
                                                ? 'text-primary font-medium'
                                                : 'hover:bg-muted hover:text-foreground',
                                        ]"
                                        @click="handleDateClick(day.date)"
                                    >
                                        {{ day.dayNum }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Right Month Calendar -->
                        <div class="w-64">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="w-6"></span>
                                <span class="text-xs font-semibold text-foreground">
                                    {{ rightMonth.name }}
                                </span>
                                <button
                                    type="button"
                                    class="cursor-pointer rounded p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                    @click="nextMonth"
                                >
                                    <ChevronRight class="size-4" />
                                </button>
                            </div>

                            <!-- Weekday Names -->
                            <div class="mb-1 grid grid-cols-7 text-center text-2xs font-medium text-muted-foreground">
                                <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
                            </div>

                            <!-- Days Grid -->
                            <div class="grid grid-cols-7 gap-y-1 text-center text-xs">
                                <div
                                    v-for="(day, idx) in rightDays"
                                    :key="idx"
                                    class="relative py-0.5"
                                >
                                    <!-- Range Highlight Background -->
                                    <div
                                        v-if="isDateInRange(day.date, tempStart, tempEnd)"
                                        class="absolute inset-y-0 bg-primary/10 dark:bg-primary/20"
                                        :class="{
                                            'rounded-s-md left-0': isStartDate(day.date, tempStart),
                                            'rounded-e-md right-0': isEndDate(day.date, tempEnd),
                                            'left-0 right-0': !isStartDate(day.date, tempStart) && !isEndDate(day.date, tempEnd),
                                        }"
                                    />

                                    <button
                                        type="button"
                                        class="relative z-10 mx-auto flex size-7 cursor-pointer items-center justify-center rounded-md text-2xs font-medium transition-colors"
                                        :class="[
                                            !day.isCurrentMonth ? 'text-muted-foreground/40' : 'text-foreground',
                                            isStartDate(day.date, tempStart) || isEndDate(day.date, tempEnd)
                                                ? 'bg-primary text-primary-foreground font-semibold shadow-xs'
                                                : isDateInRange(day.date, tempStart, tempEnd)
                                                ? 'text-primary font-medium'
                                                : 'hover:bg-muted hover:text-foreground',
                                        ]"
                                        @click="handleDateClick(day.date)"
                                    >
                                        {{ day.dayNum }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Popover Footer -->
                    <div class="flex items-center justify-between border-t border-border bg-muted/20 px-4 py-2.5 dark:border-zinc-800 dark:bg-zinc-900/40">
                        <div class="text-xs text-muted-foreground">
                            <span v-if="tempStart && tempEnd">
                                {{ formatDate(tempStart) }} - {{ formatDate(tempEnd) }}
                            </span>
                            <span v-else-if="tempStart">
                                {{ formatDate(tempStart) }} - Select end date
                            </span>
                            <span v-else>
                                Select date range
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                v-if="tempStart || tempEnd || isFiltered"
                                type="button"
                                class="cursor-pointer rounded-md px-2.5 py-1 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                @click="clearDates"
                            >
                                Reset
                            </button>
                            <button
                                type="button"
                                class="cursor-pointer rounded-md border border-input bg-background px-3 py-1 text-xs font-medium text-foreground shadow-xs transition-colors hover:bg-muted"
                                @click="cancel"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="cursor-pointer rounded-md bg-primary px-3.5 py-1 text-xs font-medium text-primary-foreground shadow-xs transition-colors hover:bg-primary/90"
                                @click="apply"
                            >
                                Apply
                            </button>
                        </div>
                    </div>
                </div>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
