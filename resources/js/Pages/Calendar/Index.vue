<script setup>
import { computed, ref } from 'vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import ruLocale from '@fullcalendar/core/locales/ru';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';
import { useWorkspaceUi } from '@/composables/useWorkspaceUi';

const store = useSkyDeskStore();
const { openEvent, openTask } = useWorkspaceUi();

const creating = ref(false);
const draft = ref({
    title: '',
    type_id: 'meeting',
    start: '',
    allDay: false,
});

const calendarEvents = computed(() => {
    const fromEvents = store.events.value.map((ev) => {
        const type = store.getEventType(ev.type_id);
        return {
            id: ev.id,
            title: ev.title,
            start: ev.start,
            end: ev.end || undefined,
            allDay: ev.allDay,
            backgroundColor: type?.color || '#6957EE',
            borderColor: type?.color || '#6957EE',
            textColor: '#fff',
            extendedProps: { kind: 'event' },
        };
    });

    const deadlines = store.tasks.value
        .filter((t) => t.deadline && !['done', 'cancelled'].includes(t.status_id))
        .filter((t) => !t.event_ids?.length)
        .map((t) => ({
            id: `deadline-${t.id}`,
            title: `⏱ ${t.title}`,
            start: t.deadline,
            allDay: !String(t.deadline).includes('T'),
            backgroundColor: 'transparent',
            borderColor: store.getPriority(t.priority_id)?.color || '#9A9BA3',
            textColor: store.getPriority(t.priority_id)?.color || '#626571',
            display: 'list-item',
            extendedProps: { kind: 'deadline', taskId: t.id },
        }));

    return [...fromEvents, ...deadlines];
});

const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    initialDate: '2026-07-31',
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
    events: calendarEvents.value,
    eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
    dateClick: (info) => {
        draft.value = {
            title: '',
            type_id: 'meeting',
            start: info.dateStr.length > 10 ? info.dateStr.slice(0, 16) : `${info.dateStr}T10:00`,
            allDay: info.allDay,
        };
        creating.value = true;
    },
    eventClick: (info) => {
        const kind = info.event.extendedProps.kind;
        if (kind === 'deadline') {
            openTask(info.event.extendedProps.taskId);
            return;
        }
        openEvent(info.event.id);
    },
}));

const createEvent = () => {
    if (!draft.value.title.trim()) return;
    const ev = store.createEvent({
        title: draft.value.title.trim(),
        type_id: draft.value.type_id,
        start: draft.value.start,
        allDay: draft.value.allDay,
    });
    creating.value = false;
    openEvent(ev.id);
};
</script>

<template>
    <AppLayout
        title="Календарь"
        subtitle="События и дедлайны поручений. Можно без задач — просто запись."
        :show-fab="false"
    >
        <template #actions>
            <v-btn color="primary" prepend-icon="mdi-plus" @click="creating = true">Новое событие</v-btn>
        </template>

        <v-card class="pa-4 pa-md-5">
            <FullCalendar :options="calendarOptions" />
        </v-card>

        <v-dialog v-model="creating" max-width="440">
            <v-card class="pa-5">
                <div class="text-h6 font-weight-bold mb-4">Новое событие</div>
                <v-text-field v-model="draft.title" label="Название" class="mb-2" />
                <v-select
                    v-model="draft.type_id"
                    :items="store.dictionaries.value.eventTypes"
                    item-title="label"
                    item-value="id"
                    label="Тип"
                    class="mb-2"
                />
                <v-text-field v-model="draft.start" type="datetime-local" label="Начало" class="mb-2" />
                <v-switch v-model="draft.allDay" label="Весь день" class="mb-2" />
                <div class="d-flex justify-end ga-2">
                    <v-btn variant="tonal" @click="creating = false">Отмена</v-btn>
                    <v-btn color="primary" @click="createEvent">Создать</v-btn>
                </div>
            </v-card>
        </v-dialog>
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
}
</style>
