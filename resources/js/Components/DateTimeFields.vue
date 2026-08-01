<script setup>
import { computed, ref, watch } from 'vue';
import { joinDateTime, normalizeTime, splitDateTime } from '@/utils/datetime';

const model = defineModel({ type: String, default: '' });

const props = defineProps({
    dateLabel: { type: String, default: 'Дата' },
    timeLabel: { type: String, default: 'Время' },
    allDay: { type: Boolean, default: false },
    density: { type: String, default: 'comfortable' },
    hideDetails: { type: [Boolean, String], default: false },
});

const date = ref('');
const time = ref('');
const timeMenu = ref(false);

const syncFromModel = () => {
    const parts = splitDateTime(model.value);
    date.value = parts.date;
    time.value = parts.time || (props.allDay ? '' : '10:00');
};

syncFromModel();

watch(() => model.value, syncFromModel);

watch(() => props.allDay, (v) => {
    if (!date.value) return;
    if (v) {
        model.value = joinDateTime(date.value, '', { allDay: true });
        return;
    }
    if (!time.value) time.value = '10:00';
    model.value = joinDateTime(date.value, time.value, { allDay: false });
});

const emitJoined = () => {
    model.value = joinDateTime(date.value, time.value, { allDay: props.allDay });
};

const toLocalDate = (value) => {
    if (!value) return null;
    if (value instanceof Date && !Number.isNaN(value.getTime())) {
        return value;
    }
    const s = String(value);
    if (/^\d{4}-\d{2}-\d{2}/.test(s)) {
        const [y, m, d] = s.slice(0, 10).split('-').map(Number);
        return new Date(y, m - 1, d);
    }
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const fromLocalDate = (value) => {
    const js = toLocalDate(value);
    if (!js) return '';
    const y = js.getFullYear();
    const m = String(js.getMonth() + 1).padStart(2, '0');
    const d = String(js.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};

const dateModel = computed({
    get: () => toLocalDate(date.value),
    set: (value) => {
        date.value = fromLocalDate(value);
        emitJoined();
    },
});

const onTimePicked = (value) => {
    time.value = normalizeTime(value) || String(value || '').slice(0, 5);
    emitJoined();
};
</script>

<template>
    <div class="d-flex flex-wrap ga-2 align-start w-100">
        <v-date-input
            v-model="dateModel"
            :label="dateLabel"
            :density="density"
            :hide-details="hideDetails"
            prepend-icon=""
            prepend-inner-icon="mdi-calendar"
            placeholder="дд.мм.гггг"
            input-format="dd.mm.yyyy"
            hide-actions
            :first-day-of-week="1"
            class="flex-grow-1 skydesk-date-input"
            style="min-width:180px"
        />

        <v-menu
            v-if="!allDay"
            v-model="timeMenu"
            :close-on-content-click="false"
            location="bottom"
        >
            <template #activator="{ props: menuProps }">
                <v-text-field
                    v-bind="menuProps"
                    :model-value="time"
                    :label="timeLabel"
                    :density="density"
                    :hide-details="hideDetails"
                    readonly
                    placeholder="14:30"
                    prepend-inner-icon="mdi-clock-outline"
                    style="width:132px;flex:0 0 132px"
                    class="skydesk-time-input"
                />
            </template>
            <v-card class="pa-2">
                <v-time-picker
                    :model-value="time || '10:00'"
                    format="24hr"
                    color="primary"
                    width="280"
                    @update:model-value="onTimePicked"
                />
            </v-card>
        </v-menu>
    </div>
</template>

<style scoped>
.skydesk-date-input :deep(input),
.skydesk-time-input :deep(input) {
    cursor: pointer;
}
</style>
