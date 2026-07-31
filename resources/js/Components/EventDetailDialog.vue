<script setup>
import { computed, reactive, watch } from 'vue';
import { useDisplay } from 'vuetify';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';

const model = defineModel({ type: Boolean, default: false });
const props = defineProps({
    eventId: { type: String, default: null },
});
const emit = defineEmits(['open-task', 'create-task']);

const { mdAndUp } = useDisplay();
const store = useSkyDeskStore();

const event = computed(() => (props.eventId ? store.getEvent(props.eventId) : null));
const linkedTasks = computed(() => (props.eventId ? store.tasksForEvent(props.eventId) : []));
const eventTypeItems = computed(() => store.dictionaries.value.eventTypes);
const availableTasks = computed(() =>
    store.tasks.value.filter((t) => !event.value?.task_ids?.includes(t.id)),
);

const form = reactive({
    title: '',
    type_id: 'other',
    start: '',
    end: '',
    allDay: false,
    place: '',
    note: '',
});

const linkTaskId = reactive({ value: null });

watch(
    () => [model.value, props.eventId],
    () => {
        if (!model.value || !event.value) return;
        form.title = event.value.title;
        form.type_id = event.value.type_id;
        form.start = event.value.start ? String(event.value.start).slice(0, 16) : '';
        form.end = event.value.end ? String(event.value.end).slice(0, 16) : '';
        form.allDay = !!event.value.allDay;
        form.place = event.value.place || '';
        form.note = event.value.note || '';
        linkTaskId.value = null;
    },
    { immediate: true },
);

watch(
    form,
    () => {
        if (!model.value || !props.eventId) return;
        store.updateEvent(props.eventId, {
            title: form.title.trim() || event.value.title,
            type_id: form.type_id,
            start: form.start || event.value.start,
            end: form.end || null,
            allDay: form.allDay,
            place: form.place,
            note: form.note,
        });
    },
    { deep: true },
);

const attachTask = () => {
    if (!linkTaskId.value) return;
    store.linkTaskEvent(linkTaskId.value, props.eventId);
    linkTaskId.value = null;
};

const detachTask = (taskId) => {
    store.unlinkTaskEvent(taskId, props.eventId);
};

const addTask = () => {
    emit('create-task', props.eventId);
};
</script>

<template>
    <v-dialog
        v-model="model"
        :fullscreen="!mdAndUp"
        :max-width="mdAndUp ? 720 : undefined"
        scrollable
    >
        <v-card v-if="event" class="d-flex flex-column" :style="mdAndUp ? 'max-height:90vh' : 'min-height:100%'">
            <v-card-title class="d-flex align-center justify-space-between px-6 pt-5">
                <div>
                    <div class="text-caption text-medium-emphasis mb-1">Событие</div>
                    <span class="text-h6 font-weight-bold">Карточка</span>
                </div>
                <v-btn icon variant="tonal" size="small" @click="model = false">
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </v-card-title>
            <v-divider />
            <v-card-text class="px-6 py-5">
                <v-text-field v-model="form.title" label="Название" class="mb-2" />
                <v-row dense>
                    <v-col cols="12" sm="6">
                        <v-select
                            v-model="form.type_id"
                            :items="eventTypeItems"
                            item-title="label"
                            item-value="id"
                            label="Тип"
                        />
                    </v-col>
                    <v-col cols="12" sm="6">
                        <v-switch v-model="form.allDay" label="Весь день" hide-details />
                    </v-col>
                    <v-col cols="12" sm="6">
                        <v-text-field v-model="form.start" type="datetime-local" label="Начало" />
                    </v-col>
                    <v-col cols="12" sm="6">
                        <v-text-field v-model="form.end" type="datetime-local" label="Конец" />
                    </v-col>
                    <v-col cols="12">
                        <v-text-field v-model="form.place" label="Место" />
                    </v-col>
                </v-row>
                <v-textarea v-model="form.note" label="Заметка" rows="2" class="mb-4" />

                <div class="d-flex align-center justify-space-between mb-2">
                    <h3 class="text-subtitle-2 font-weight-bold mb-0">Поручения</h3>
                    <v-btn size="small" color="primary" variant="tonal" prepend-icon="mdi-plus" @click="addTask">
                        Добавить задачу
                    </v-btn>
                </div>

                <div v-if="!linkedTasks.length" class="text-caption text-medium-emphasis mb-3">
                    Пока без поручений — можно добавить
                </div>
                <div
                    v-for="t in linkedTasks"
                    :key="t.id"
                    class="d-flex align-center ga-2 px-3 py-2 mb-1 skydesk-accent-panel"
                    style="cursor:pointer"
                    @click="emit('open-task', t.id)"
                >
                    <div class="flex-grow-1 text-body-2 font-weight-medium">{{ t.title }}</div>
                    <v-chip size="x-small" variant="tonal">{{ store.getStatus(t.status_id)?.label }}</v-chip>
                    <v-btn icon size="x-small" variant="text" @click.stop="detachTask(t.id)">
                        <v-icon size="16">mdi-link-off</v-icon>
                    </v-btn>
                </div>

                <div v-if="availableTasks.length" class="d-flex ga-2 mt-3 align-center">
                    <v-select
                        v-model="linkTaskId.value"
                        :items="availableTasks"
                        item-title="title"
                        item-value="id"
                        label="Привязать существующее"
                        density="compact"
                        hide-details
                        class="flex-grow-1"
                    />
                    <v-btn color="primary" variant="tonal" :disabled="!linkTaskId.value" @click="attachTask">
                        Привязать
                    </v-btn>
                </div>
            </v-card-text>
        </v-card>
    </v-dialog>
</template>
