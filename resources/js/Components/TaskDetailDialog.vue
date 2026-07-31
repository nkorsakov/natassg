<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useDisplay } from 'vuetify';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';

const model = defineModel({ type: Boolean, default: false });
const props = defineProps({
    taskId: { type: String, default: null },
    parentId: { type: String, default: null },
});
const emit = defineEmits(['open-task', 'open-event', 'open-advance', 'created']);

const { mdAndUp } = useDisplay();
const store = useSkyDeskStore();

const confirmClose = ref(false);
const linkEventId = ref(null);
const showNewEvent = ref(false);
const newEvent = reactive({
    title: '',
    type_id: 'meeting',
    start: '',
    end: '',
    allDay: false,
    place: '',
});

const task = computed(() => (props.taskId ? store.getTask(props.taskId) : null));

const form = reactive({
    title: '',
    status_id: 'new',
    priority_id: 'normal',
    type_id: 'purchase',
    deadline: '',
    note: '',
});

watch(
    () => [model.value, props.taskId],
    () => {
        if (!model.value || !task.value) return;
        form.title = task.value.title;
        form.status_id = task.value.status_id;
        form.priority_id = task.value.priority_id;
        form.type_id = task.value.type_id;
        form.deadline = task.value.deadline
            ? task.value.deadline.slice(0, 16)
            : '';
        form.note = task.value.note || '';
        linkEventId.value = null;
        showNewEvent.value = false;
    },
    { immediate: true },
);

const children = computed(() => (props.taskId ? store.childrenOf(props.taskId) : []));
const linkedEvents = computed(() => (props.taskId ? store.eventsForTask(props.taskId) : []));
const linkedAdvances = computed(() => (props.taskId ? store.advancesForTask(props.taskId) : []));
const availableEvents = computed(() =>
    store.events.value.filter((e) => !task.value?.event_ids?.includes(e.id)),
);

const statusItems = computed(() => store.dictionaries.value.statuses);
const priorityItems = computed(() => store.dictionaries.value.priorities);
const typeItems = computed(() => store.dictionaries.value.taskTypes);
const eventTypeItems = computed(() => store.dictionaries.value.eventTypes);

const save = () => {
    if (!props.taskId) return;
    store.updateTask(props.taskId, {
        title: form.title.trim() || task.value.title,
        status_id: form.status_id,
        priority_id: form.priority_id,
        type_id: form.type_id,
        deadline: form.deadline || null,
        note: form.note,
    });
};

const addChild = () => {
    const child = store.createTask({
        title: 'Новая подзадача',
        parent_id: props.taskId,
        status_id: 'new',
    });
    emit('open-task', child.id);
};

const promote = () => {
    store.makeTaskRoot(props.taskId);
};

const requestClose = () => {
    if (children.value.length || store.descendantsOf(props.taskId).length) {
        confirmClose.value = true;
    } else {
        store.closeTaskCascade(props.taskId);
        form.status_id = 'done';
    }
};

const confirmCascadeClose = () => {
    store.closeTaskCascade(props.taskId);
    form.status_id = 'done';
    confirmClose.value = false;
};

const attachEvent = () => {
    if (!linkEventId.value) return;
    store.linkTaskEvent(props.taskId, linkEventId.value);
    linkEventId.value = null;
};

const createAndAttachEvent = () => {
    if (!newEvent.title.trim()) return;
    const ev = store.createEvent({
        ...newEvent,
        title: newEvent.title.trim(),
        task_ids: [props.taskId],
    });
    showNewEvent.value = false;
    newEvent.title = '';
    emit('open-event', ev.id);
};

const detachEvent = (eventId) => {
    store.unlinkTaskEvent(props.taskId, eventId);
};

const createAdvance = () => {
    const adv = store.createAdvance({
        title: `Аванс: ${task.value?.title || ''}`,
        task_id: props.taskId,
        amount: 10000,
        status_id: 'pending',
    });
    emit('open-advance', adv.id);
};

watch(
    form,
    () => {
        if (model.value && props.taskId) save();
    },
    { deep: true },
);
</script>

<template>
    <v-dialog
        v-model="model"
        :fullscreen="!mdAndUp"
        :max-width="mdAndUp ? 920 : undefined"
        scrollable
        :transition="mdAndUp ? 'dialog-transition' : 'dialog-bottom-transition'"
    >
        <v-card v-if="task" class="d-flex flex-column" :style="mdAndUp ? 'max-height:90vh' : 'min-height:100%'">
            <v-card-title class="d-flex align-center justify-space-between px-6 pt-5 flex-wrap ga-2">
                <div>
                    <div class="text-caption text-medium-emphasis mb-1">Поручение</div>
                    <span class="text-h6 font-weight-bold">Карточка</span>
                </div>
                <div class="d-flex ga-2">
                    <v-btn
                        v-if="task.parent_id"
                        size="small"
                        variant="tonal"
                        prepend-icon="mdi-arrow-up-bold"
                        @click="promote"
                    >
                        Сделать основной
                    </v-btn>
                    <v-btn
                        v-if="task.status_id !== 'done'"
                        size="small"
                        variant="tonal"
                        color="success"
                        @click="requestClose"
                    >
                        Закрыть
                    </v-btn>
                    <v-btn icon variant="tonal" size="small" @click="model = false">
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                </div>
            </v-card-title>
            <v-divider />

            <v-card-text class="px-6 py-5">
                <v-text-field v-model="form.title" label="Название" class="mb-2" />
                <v-row dense>
                    <v-col cols="12" sm="4">
                        <v-select
                            v-model="form.status_id"
                            :items="statusItems"
                            item-title="label"
                            item-value="id"
                            label="Статус"
                        />
                    </v-col>
                    <v-col cols="12" sm="4">
                        <v-select
                            v-model="form.priority_id"
                            :items="priorityItems"
                            item-title="label"
                            item-value="id"
                            label="Приоритет"
                        />
                    </v-col>
                    <v-col cols="12" sm="4">
                        <v-select
                            v-model="form.type_id"
                            :items="typeItems"
                            item-title="label"
                            item-value="id"
                            label="Тип"
                        />
                    </v-col>
                    <v-col cols="12" sm="6">
                        <v-text-field
                            v-model="form.deadline"
                            type="datetime-local"
                            label="Дедлайн (можно в календарь)"
                        />
                    </v-col>
                </v-row>
                <v-textarea v-model="form.note" label="Заметка" rows="2" auto-grow class="mb-4" />

                <!-- Subtasks -->
                <div class="d-flex align-center justify-space-between mb-2">
                    <h3 class="text-subtitle-2 font-weight-bold mb-0">Подзадачи</h3>
                    <v-btn size="small" variant="tonal" prepend-icon="mdi-plus" @click="addChild">
                        Добавить
                    </v-btn>
                </div>
                <div v-if="!children.length" class="text-caption text-medium-emphasis mb-4">
                    Нет подзадач
                </div>
                <div v-else class="mb-4">
                    <div
                        v-for="child in children"
                        :key="child.id"
                        class="d-flex align-center ga-2 px-3 py-2 mb-1 skydesk-accent-panel"
                        style="cursor:pointer"
                        @click="emit('open-task', child.id)"
                    >
                        <v-icon size="16" :color="store.getStatus(child.status_id)?.color">
                            mdi-subdirectory-arrow-right
                        </v-icon>
                        <div class="flex-grow-1 text-body-2 font-weight-medium">{{ child.title }}</div>
                        <v-chip size="x-small" variant="tonal" :color="store.getStatus(child.status_id)?.color">
                            {{ store.getStatus(child.status_id)?.label }}
                        </v-chip>
                    </div>
                </div>

                <!-- Events -->
                <div class="d-flex align-center justify-space-between mb-2">
                    <h3 class="text-subtitle-2 font-weight-bold mb-0">События</h3>
                    <v-btn size="small" variant="tonal" prepend-icon="mdi-plus" @click="showNewEvent = !showNewEvent">
                        Новое
                    </v-btn>
                </div>
                <div v-if="linkedEvents.length" class="mb-2">
                    <div
                        v-for="ev in linkedEvents"
                        :key="ev.id"
                        class="d-flex align-center ga-2 px-3 py-2 mb-1"
                        style="border-radius:11px;background:rgba(var(--v-theme-primary),.06);cursor:pointer"
                        @click="emit('open-event', ev.id)"
                    >
                        <v-icon size="16">mdi-calendar</v-icon>
                        <div class="flex-grow-1 text-body-2">{{ ev.title }}</div>
                        <v-btn icon size="x-small" variant="text" @click.stop="detachEvent(ev.id)">
                            <v-icon size="16">mdi-link-off</v-icon>
                        </v-btn>
                    </div>
                </div>
                <div v-if="availableEvents.length" class="d-flex ga-2 mb-4 align-center">
                    <v-select
                        v-model="linkEventId"
                        :items="availableEvents"
                        item-title="title"
                        item-value="id"
                        label="Привязать существующее"
                        density="compact"
                        hide-details
                        class="flex-grow-1"
                    />
                    <v-btn color="primary" variant="tonal" :disabled="!linkEventId" @click="attachEvent">
                        Привязать
                    </v-btn>
                </div>
                <v-card v-if="showNewEvent" variant="outlined" class="pa-4 mb-4">
                    <v-text-field v-model="newEvent.title" label="Название события" density="compact" />
                    <v-row dense>
                        <v-col cols="6">
                            <v-select
                                v-model="newEvent.type_id"
                                :items="eventTypeItems"
                                item-title="label"
                                item-value="id"
                                label="Тип"
                                density="compact"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-switch v-model="newEvent.allDay" label="Весь день" density="compact" hide-details />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field v-model="newEvent.start" type="datetime-local" label="Начало" density="compact" />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field v-model="newEvent.end" type="datetime-local" label="Конец" density="compact" />
                        </v-col>
                    </v-row>
                    <v-btn color="primary" size="small" @click="createAndAttachEvent">Создать и привязать</v-btn>
                </v-card>

                <!-- Advances -->
                <div class="d-flex align-center justify-space-between mb-2">
                    <h3 class="text-subtitle-2 font-weight-bold mb-0">Авансы</h3>
                    <v-btn size="small" variant="tonal" prepend-icon="mdi-cash-plus" @click="createAdvance">
                        Запросить деньги
                    </v-btn>
                </div>
                <div v-if="!linkedAdvances.length" class="text-caption text-medium-emphasis">Нет заявок</div>
                <div
                    v-for="adv in linkedAdvances"
                    :key="adv.id"
                    class="d-flex align-center ga-2 px-3 py-2 mb-1 skydesk-accent-panel"
                    style="cursor:pointer"
                    @click="emit('open-advance', adv.id)"
                >
                    <div class="flex-grow-1">
                        <div class="text-body-2 font-weight-bold">{{ adv.title }}</div>
                        <div class="text-caption text-medium-emphasis">
                            {{ store.formatMoney(adv.amount) }} · {{ store.getAdvanceStatus(adv.status_id)?.label }}
                        </div>
                    </div>
                    <v-icon size="18">mdi-chevron-right</v-icon>
                </div>
            </v-card-text>
        </v-card>
    </v-dialog>

    <v-dialog v-model="confirmClose" max-width="420">
        <v-card class="pa-5">
            <div class="text-h6 font-weight-bold mb-2">Закрыть поручение?</div>
            <p class="text-body-2 text-medium-emphasis mb-4">
                Все вложенные подзадачи тоже будут закрыты.
            </p>
            <div class="d-flex justify-end ga-2">
                <v-btn variant="tonal" @click="confirmClose = false">Отмена</v-btn>
                <v-btn color="primary" @click="confirmCascadeClose">Закрыть всё</v-btn>
            </div>
        </v-card>
    </v-dialog>
</template>
