<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import ruLocale from '@fullcalendar/core/locales/ru';
import AppLayout from '@/Layouts/AppLayout.vue';
import DateTimeFields from '@/Components/DateTimeFields.vue';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';
import { useWorkspaceUi } from '@/composables/useWorkspaceUi';
import { useIsAdmin } from '@/composables/useIsAdmin';

const store = useSkyDeskStore();
const { openEvent, openTask } = useWorkspaceUi();
const page = usePage();
const { isAdmin } = useIsAdmin();

const initialView = (() => {
    const params = new URLSearchParams(String(page.url || '').split('?')[1] || '');
    return params.get('view') === 'list' ? 'listFourWeeks' : 'dayGridMonth';
})();

const creating = ref(false);
const draft = ref({
    title: '',
    type_id: 'meeting',
    start: '',
    allDay: false,
});

const ownerPrefix = (item) => {
    if (!isAdmin.value || !item?.user) return '';
    const tag = item.user.initials || item.user.name;
    return tag ? `${tag}: ` : '';
};

const calendarEvents = computed(() => {
    const fromEvents = store.events.value.map((ev) => {
        const type = store.getEventType(ev.type_id);
        return {
            id: ev.id,
            title: `${ownerPrefix(ev)}${ev.title}`,
            start: ev.start,
            end: ev.end || undefined,
            allDay: ev.allDay,
            backgroundColor: type?.color || '#6957EE',
            borderColor: type?.color || '#6957EE',
            textColor: '#fff',
            extendedProps: { kind: 'event', user: ev.user },
        };
    });

    const deadlines = store.tasks.value
        .filter((t) => t.deadline && !['done', 'cancelled'].includes(t.status_id))
        .filter((t) => !t.event_ids?.length)
        .map((t) => ({
            id: `deadline-${t.id}`,
            title: `⏱ ${ownerPrefix(t)}${t.title}`,
            start: t.deadline,
            allDay: !String(t.deadline).includes('T'),
            backgroundColor: 'transparent',
            borderColor: store.getPriority(t.priority_id)?.color || '#9A9BA3',
            textColor: store.getPriority(t.priority_id)?.color || '#626571',
            display: 'list-item',
            extendedProps: { kind: 'deadline', taskId: t.id, user: t.user },
        }));

    return [...fromEvents, ...deadlines];
});

const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
    initialView,
    initialDate: new Date().toISOString().slice(0, 10),
    locale: ruLocale,
    headerToolbar: {
        left: 'prev title next',
        center: '',
        right: 'dayGridMonth,timeGridWeek,timeGridDay,listFourWeeks today',
    },
    buttonText: {
        today: 'Сегодня',
        month: 'Мес',
        week: 'Нед',
        day: 'День',
        list: 'Список',
    },
    views: {
        listFourWeeks: {
            type: 'list',
            duration: { weeks: 4 },
            buttonText: 'Список',
        },
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
            allDay: false,
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

const createEvent = async () => {
    if (!draft.value.title.trim()) return;
    await store.createEvent({
        title: draft.value.title.trim(),
        type_id: draft.value.type_id,
        start: draft.value.start,
        allDay: draft.value.allDay,
    });
    creating.value = false;
};
</script>

<template>
    <AppLayout
        title="Календарь"
        subtitle="События и дедлайны поручений. Можно без задач — просто запись."
        :show-fab="false"
    >
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
                <DateTimeFields v-model="draft.start" :all-day="draft.allDay" class="mb-2" />
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
    --fc-button-bg-color: transparent;
    --fc-button-border-color: rgba(var(--v-theme-on-surface), 0.16);
    --fc-button-hover-bg-color: rgba(var(--v-theme-on-surface), 0.06);
    --fc-button-hover-border-color: rgba(var(--v-theme-on-surface), 0.22);
    --fc-button-active-bg-color: rgba(var(--v-theme-primary), 0.12);
    --fc-button-active-border-color: rgba(var(--v-theme-primary), 0.28);
    --fc-button-text-color: rgb(var(--v-theme-on-surface));
    --fc-today-bg-color: rgba(var(--v-theme-primary), 0.1);
    --fc-event-border-color: transparent;
    --fc-page-bg-color: transparent;
    --fc-neutral-bg-color: rgb(var(--v-theme-background));
    --fc-list-event-hover-bg-color: rgba(var(--v-theme-primary), 0.06);
    font-family: inherit;
}

.fc .fc-toolbar {
    gap: 0.5rem 0.75rem;
    margin-bottom: 0.85rem !important;
    flex-wrap: wrap;
    align-items: center;
}

.fc .fc-toolbar-chunk:first-child {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.fc .fc-toolbar-title {
    font-size: 0.95rem;
    font-weight: 700;
    letter-spacing: -0.02em;
    color: rgb(var(--v-theme-on-surface));
    line-height: 1.2;
    margin: 0;
    min-width: 9.5rem;
    text-align: center;
}

.fc .fc-button {
    border-radius: 8px !important;
    font-size: 0.75rem !important;
    font-weight: 600;
    line-height: 1.2;
    text-transform: none;
    letter-spacing: 0;
    box-shadow: none !important;
    padding: 0.28em 0.6em !important;
    height: auto !important;
}

.fc .fc-button:focus {
    box-shadow: none !important;
}

.fc .fc-button-primary:not(:disabled).fc-button-active,
.fc .fc-button-primary:not(:disabled):active {
    color: rgb(var(--v-theme-primary));
    font-weight: 700;
}

.fc .fc-button-group {
    display: inline-flex;
}

.fc .fc-button-group > .fc-button {
    border-radius: 0 !important;
    margin: 0 !important;
}

.fc .fc-button-group > .fc-button:first-child {
    border-radius: 8px 0 0 8px !important;
}

.fc .fc-button-group > .fc-button:last-child {
    border-radius: 0 8px 8px 0 !important;
}

.fc .fc-button-group > .fc-button:only-child {
    border-radius: 8px !important;
}

.fc .fc-prev-button,
.fc .fc-next-button {
    padding-inline: 0.45em !important;
    min-width: 1.85rem;
    border-radius: 8px !important;
}

.fc .fc-today-button {
    margin-left: 0.65rem !important;
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
        gap: 0.45rem;
        align-items: stretch;
    }

    .fc .fc-toolbar-chunk {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.35rem;
    }

    .fc .fc-toolbar-title {
        font-size: 0.9rem;
        text-align: center;
    }
}
</style>
