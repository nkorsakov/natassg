<script setup>
import { computed } from 'vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import ruLocale from '@fullcalendar/core/locales/ru';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    events: { type: Array, default: () => [] },
    initialDate: { type: String, default: '2026-07-31' },
});

const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    initialDate: props.initialDate,
    locale: ruLocale,
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
    },
    buttonText: {
        today: 'Сегодня',
        month: 'Месяц',
        week: 'Неделя',
        day: 'День',
        list: 'Список',
    },
    height: 'auto',
    expandRows: true,
    nowIndicator: true,
    navLinks: true,
    editable: false,
    dayMaxEvents: 3,
    firstDay: 1,
    slotMinTime: '08:00:00',
    slotMaxTime: '22:00:00',
    events: props.events,
    eventTimeFormat: {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    },
}));
</script>

<template>
    <AppLayout
        title="Календарь"
        subtitle="Поездки, встречи и всё, что привязано ко времени."
        :show-fab="false"
    >
        <template #actions>
            <v-btn color="primary" prepend-icon="mdi-plus">Новое событие</v-btn>
        </template>

        <v-card class="pa-4 pa-md-5">
            <FullCalendar :options="calendarOptions" />
        </v-card>
    </AppLayout>
</template>

<style>
.fc {
    --fc-border-color: rgba(var(--v-border-color), var(--v-border-opacity));
    --fc-button-bg-color: rgb(var(--v-theme-primary));
    --fc-button-border-color: rgb(var(--v-theme-primary));
    --fc-button-hover-bg-color: rgba(var(--v-theme-primary), 0.85);
    --fc-button-hover-border-color: rgba(var(--v-theme-primary), 0.85);
    --fc-button-active-bg-color: rgba(var(--v-theme-primary), 0.75);
    --fc-button-active-border-color: rgba(var(--v-theme-primary), 0.75);
    --fc-today-bg-color: rgba(var(--v-theme-primary), 0.1);
    --fc-event-border-color: transparent;
    --fc-page-bg-color: transparent;
    --fc-neutral-bg-color: rgb(var(--v-theme-background));
    --fc-list-event-hover-bg-color: rgba(var(--v-theme-primary), 0.06);
    --fc-button-text-color: rgb(var(--v-theme-on-primary));
    font-family: inherit;
}

.fc .fc-toolbar-title {
    font-size: 1.15rem;
    font-weight: 700;
    letter-spacing: -0.02em;
    color: rgb(var(--v-theme-on-surface));
}

.fc .fc-button {
    border-radius: 10px !important;
    font-weight: 600;
    text-transform: none;
    box-shadow: none !important;
    padding: 0.4em 0.75em;
}

.fc .fc-button-primary:not(:disabled).fc-button-active,
.fc .fc-button-primary:not(:disabled):active {
    background: rgba(var(--v-theme-primary), 0.75);
    border-color: rgba(var(--v-theme-primary), 0.75);
}

.fc .fc-daygrid-day-number,
.fc .fc-col-header-cell-cushion {
    color: rgb(var(--v-theme-on-surface));
    text-decoration: none;
    font-weight: 600;
}

.fc .fc-event {
    border-radius: 4px;
    border: none;
    padding: 1px 4px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}

.fc .fc-list-event-dot {
    border-color: rgb(var(--v-theme-primary));
}

.fc .fc-list-day-cushion,
.fc .fc-list-table td {
    background: transparent;
}

@media (max-width: 959px) {
    .fc .fc-toolbar {
        flex-direction: column;
        gap: 0.75rem;
        align-items: stretch;
    }

    .fc .fc-toolbar-chunk {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 0.25rem;
    }

    .fc .fc-toolbar-title {
        text-align: center;
    }
}
</style>
