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
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue?: { start: Date; end: Date };
        defaultLabel?: string;
    }>(),
    {
        modelValue: () => {
            const end = new Date(2026, 7, 24); // Aug 24, 2026
            const start = new Date(2026, 6, 25); // Jul 25, 2026
            return { start, end };
        },
        defaultLabel: 'Jul 25 - Aug 24, 2026',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: { start: Date; end: Date }];
    change: [value: { start: Date; end: Date; label: string }];
}>();

const isOpen = ref(false);

const activePreset = ref<string>('custom');

const currentStart = ref<Date>(new Date(props.modelValue.start));
const currentEnd = ref<Date>(new Date(props.modelValue.end));

const tempStart = ref<Date | null>(new Date(props.modelValue.start));
const tempEnd = ref<Date | null>(new Date(props.modelValue.end));

// Display Month in calendar (left calendar month)
const viewDate = ref<Date>(new Date(props.modelValue.start.getFullYear(), props.modelValue.start.getMonth(), 1));

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
        tempStart.value = date;
        tempEnd.value = null;
    } else if (tempStart.value && !tempEnd.value) {
        if (date < tempStart.value) {
            tempEnd.value = tempStart.value;
            tempStart.value = date;
        } else {
            tempEnd.value = date;
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
    { key: 'custom', label: 'Custom Range' },
];

function selectPreset(presetKey: string) {
    activePreset.value = presetKey;
    const now = new Date(2026, 7, 24); // Reference time matching demo context

    if (presetKey === 'today') {
        tempStart.value = new Date(now);
        tempEnd.value = new Date(now);
    } else if (presetKey === 'yesterday') {
        const y = new Date(now);
        y.setDate(y.getDate() - 1);
        tempStart.value = y;
        tempEnd.value = y;
    } else if (presetKey === 'last_7_days') {
        const s = new Date(now);
        s.setDate(s.getDate() - 6);
        tempStart.value = s;
        tempEnd.value = new Date(now);
    } else if (presetKey === 'last_30_days') {
        const s = new Date(now);
        s.setDate(s.getDate() - 29);
        tempStart.value = s;
        tempEnd.value = new Date(now);
    } else if (presetKey === 'this_month') {
        tempStart.value = new Date(now.getFullYear(), now.getMonth(), 1);
        tempEnd.value = new Date(now);
    } else if (presetKey === 'last_month') {
        tempStart.value = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        tempEnd.value = new Date(now.getFullYear(), now.getMonth(), 0);
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
            return `${shortMonthNames[currentStart.value.getMonth()]} ${currentStart.value.getDate()} - ${shortMonthNames[currentEnd.value.getMonth()]} ${currentEnd.value.getDate()}, ${currentStart.value.getFullYear()}`;
        }
        return `${formatDate(currentStart.value)} - ${formatDate(currentEnd.value)}`;
    }
    return props.defaultLabel;
});

function cancel() {
    tempStart.value = new Date(currentStart.value);
    tempEnd.value = new Date(currentEnd.value);
    isOpen.value = false;
}

function apply() {
    if (tempStart.value) {
        currentStart.value = new Date(tempStart.value);
        currentEnd.value = tempEnd.value ? new Date(tempEnd.value) : new Date(tempStart.value);
        const value = { start: currentStart.value, end: currentEnd.value };
        emit('update:modelValue', value);
        emit('change', { ...value, label: displayLabel.value });
    }
    isOpen.value = false;
}
</script>

<template>
    <PopoverRoot v-model:open="isOpen">
        <PopoverTrigger as-child>
            <button
                type="button"
                class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-zinc-200 bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800"
            >
                <CalendarIcon class="size-3.5 text-zinc-400" />
                <span>{{ displayLabel }}</span>
                <ChevronDown class="size-3.5 text-zinc-400 transition-transform duration-200" :class="isOpen ? 'rotate-180' : ''" />
            </button>
        </PopoverTrigger>

        <PopoverPortal>
            <PopoverContent
                align="end"
                :side-offset="6"
                class="z-50 flex flex-col overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-[#121215] sm:flex-row"
            >
                <!-- Presets Sidebar -->
                <div class="w-full border-b border-zinc-200 p-3 dark:border-zinc-800 sm:w-44 sm:border-b-0 sm:border-e">
                    <div class="space-y-1">
                        <button
                            v-for="preset in presets"
                            :key="preset.key"
                            type="button"
                            class="flex w-full cursor-pointer items-center justify-between rounded-md px-2.5 py-1.5 text-left text-xs font-medium transition-colors"
                            :class="
                                activePreset === preset.key
                                    ? 'bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400'
                                    : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white'
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
                                    class="cursor-pointer rounded p-1 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                                    @click="prevMonth"
                                >
                                    <ChevronLeft class="size-4" />
                                </button>
                                <span class="text-xs font-semibold text-zinc-900 dark:text-white">
                                    {{ leftMonth.name }}
                                </span>
                                <span class="w-6"></span>
                            </div>

                            <!-- Weekday Names -->
                            <div class="mb-1 grid grid-cols-7 text-center text-2xs font-medium text-zinc-400">
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
                                        class="absolute inset-y-0 bg-blue-50 dark:bg-blue-950/40"
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
                                            !day.isCurrentMonth ? 'text-zinc-300 dark:text-zinc-600' : 'text-zinc-700 dark:text-zinc-300',
                                            isStartDate(day.date, tempStart) || isEndDate(day.date, tempEnd)
                                                ? 'bg-blue-600 text-white font-semibold shadow-xs'
                                                : isDateInRange(day.date, tempStart, tempEnd)
                                                ? 'text-blue-600 dark:text-blue-400'
                                                : 'hover:bg-zinc-100 dark:hover:bg-zinc-800 hover:text-zinc-900 dark:hover:text-white',
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
                                <span class="text-xs font-semibold text-zinc-900 dark:text-white">
                                    {{ rightMonth.name }}
                                </span>
                                <button
                                    type="button"
                                    class="cursor-pointer rounded p-1 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                                    @click="nextMonth"
                                >
                                    <ChevronRight class="size-4" />
                                </button>
                            </div>

                            <!-- Weekday Names -->
                            <div class="mb-1 grid grid-cols-7 text-center text-2xs font-medium text-zinc-400">
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
                                        class="absolute inset-y-0 bg-blue-50 dark:bg-blue-950/40"
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
                                            !day.isCurrentMonth ? 'text-zinc-300 dark:text-zinc-600' : 'text-zinc-700 dark:text-zinc-300',
                                            isStartDate(day.date, tempStart) || isEndDate(day.date, tempEnd)
                                                ? 'bg-blue-600 text-white font-semibold shadow-xs'
                                                : isDateInRange(day.date, tempStart, tempEnd)
                                                ? 'text-blue-600 dark:text-blue-400'
                                                : 'hover:bg-zinc-100 dark:hover:bg-zinc-800 hover:text-zinc-900 dark:hover:text-white',
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
                    <div class="flex items-center justify-between border-t border-zinc-200 bg-zinc-50/50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900/40">
                        <div class="text-xs text-zinc-500">
                            <span v-if="tempStart && tempEnd">
                                {{ formatDate(tempStart) }} - {{ formatDate(tempEnd) }}
                            </span>
                            <span v-else-if="tempStart">
                                Select end date
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="cursor-pointer rounded-md border border-zinc-200 bg-white px-3 py-1 text-xs font-medium text-zinc-700 shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                                @click="cancel"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="cursor-pointer rounded-md bg-blue-600 px-3.5 py-1 text-xs font-medium text-white shadow-xs transition-colors hover:bg-blue-700"
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
