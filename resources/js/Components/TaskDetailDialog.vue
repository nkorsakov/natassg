<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useDisplay } from 'vuetify';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';
import { prepareUploadFile } from '@/utils/compressImage';

const model = defineModel({ type: Boolean, default: false });
const props = defineProps({
    taskId: { type: [String, Number], default: null },
});
const emit = defineEmits(['open-task', 'open-event', 'open-advance']);

const { mdAndUp } = useDisplay();
const store = useSkyDeskStore();

const confirmClose = ref(false);
const linkEventId = ref(null);
const showNewEvent = ref(false);
const showDeadline = ref(false);
const showPriority = ref(false);
const showType = ref(false);
const showNote = ref(false);
const showAttachEvent = ref(false);
const uploading = ref(false);
const fileInput = ref(null);

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

const syncForm = () => {
    if (!task.value) return;
    form.title = task.value.title;
    form.status_id = task.value.status_id;
    form.priority_id = task.value.priority_id;
    form.type_id = task.value.type_id;
    form.deadline = task.value.deadline ? String(task.value.deadline).slice(0, 16) : '';
    form.note = task.value.note || '';
    showDeadline.value = !!task.value.deadline;
    showPriority.value = task.value.priority_id !== 'normal';
    showType.value = false;
    showNote.value = !!task.value.note;
    showNewEvent.value = false;
    showAttachEvent.value = false;
    linkEventId.value = null;
};

watch(
    () => [model.value, props.taskId],
    () => {
        if (model.value && task.value) syncForm();
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

const addOptions = computed(() => {
    const opts = [];
    if (!showDeadline.value) opts.push({ id: 'deadline', label: 'Дедлайн', icon: 'mdi-clock-outline' });
    if (!showPriority.value) opts.push({ id: 'priority', label: 'Приоритет', icon: 'mdi-flag-outline' });
    if (!showType.value) opts.push({ id: 'type', label: 'Тип', icon: 'mdi-shape-outline' });
    if (!showNote.value) opts.push({ id: 'note', label: 'Заметка', icon: 'mdi-note-text-outline' });
    opts.push({ id: 'subtask', label: 'Подзадача', icon: 'mdi-file-tree-outline' });
    opts.push({ id: 'event', label: 'Событие', icon: 'mdi-calendar-plus' });
    opts.push({ id: 'advance', label: 'Аванс', icon: 'mdi-cash-plus' });
    opts.push({ id: 'file', label: 'Файл / фото', icon: 'mdi-paperclip' });
    return opts;
});

const attachments = computed(() => task.value?.attachments || []);

const save = () => {
    if (!props.taskId || !task.value) return;
    store.updateTask(props.taskId, {
        title: form.title.trim() || task.value.title,
        status_id: form.status_id,
        priority_id: form.priority_id,
        type_id: form.type_id,
        deadline: form.deadline || null,
        note: form.note,
    });
};

watch(form, () => {
    if (model.value && props.taskId) save();
}, { deep: true });

const onAdd = async (id) => {
    if (id === 'deadline') {
        showDeadline.value = true;
        if (!form.deadline) {
            const d = new Date();
            d.setMinutes(0, 0, 0);
            form.deadline = d.toISOString().slice(0, 16);
        }
    } else if (id === 'priority') {
        showPriority.value = true;
        form.priority_id = 'high';
    } else if (id === 'type') {
        showType.value = true;
    } else if (id === 'note') {
        showNote.value = true;
    } else if (id === 'subtask') {
        const child = await store.createTask({
            title: 'Новая подзадача',
            parent_id: props.taskId,
            status_id: 'new',
        });
        if (child?.id) emit('open-task', child.id);
    } else if (id === 'event') {
        showNewEvent.value = true;
        showAttachEvent.value = true;
        newEvent.title = '';
        newEvent.start = form.deadline || new Date().toISOString().slice(0, 16);
    } else if (id === 'advance') {
        const adv = await store.createAdvance({
            title: `Аванс: ${task.value?.title || ''}`,
            task_id: props.taskId,
            amount: 10000,
            status_id: 'pending',
        });
        if (adv?.id) emit('open-advance', adv.id);
    } else if (id === 'file') {
        fileInput.value?.click();
    }
};

const onFilesSelected = async (event) => {
    const files = [...(event.target.files || [])];
    event.target.value = '';
    if (!files.length || !props.taskId) return;
    uploading.value = true;
    try {
        for (const raw of files) {
            const prepared = await prepareUploadFile(raw);
            store.uploadTaskAttachment(props.taskId, prepared.file, {
                width: prepared.width,
                height: prepared.height,
            });
        }
    } finally {
        uploading.value = false;
    }
};

const removeAttachment = (attachmentId) => {
    store.removeTaskAttachment(props.taskId, attachmentId);
};

const promote = () => store.makeTaskRoot(props.taskId);

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

const createAndAttachEvent = async () => {
    if (!newEvent.title.trim()) return;
    const ev = await store.createEvent({
        ...newEvent,
        title: newEvent.title.trim(),
        task_ids: [props.taskId],
    });
    showNewEvent.value = false;
    showAttachEvent.value = false;
    newEvent.title = '';
    if (ev?.id) emit('open-event', ev.id);
};

const detachEvent = (eventId) => {
    store.unlinkTaskEvent(props.taskId, eventId);
};

const clearDeadline = () => {
    form.deadline = '';
    showDeadline.value = false;
};
</script>

<template>
    <v-dialog
        v-model="model"
        :fullscreen="!mdAndUp"
        :max-width="mdAndUp ? 640 : undefined"
        scrollable
        :transition="mdAndUp ? 'dialog-transition' : 'dialog-bottom-transition'"
    >
        <v-card v-if="task" class="d-flex flex-column" :style="mdAndUp ? 'max-height:90vh' : 'min-height:100%'">
            <v-card-title class="d-flex align-center justify-space-between px-6 pt-5 flex-wrap ga-2">
                <div class="text-h6 font-weight-bold">Поручение</div>
                <div class="d-flex ga-2">
                    <v-btn
                        v-if="task.parent_id"
                        size="small"
                        variant="tonal"
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
                        Готово
                    </v-btn>
                    <v-btn icon variant="tonal" size="small" @click="model = false">
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                </div>
            </v-card-title>
            <v-divider />

            <v-card-text class="px-6 py-5">
                <v-text-field
                    v-model="form.title"
                    label="Что сделать"
                    hide-details
                    class="mb-4"
                    autofocus
                />

                <div class="d-flex align-center flex-wrap ga-2 mb-4">
                    <v-select
                        v-model="form.status_id"
                        :items="statusItems"
                        item-title="label"
                        item-value="id"
                        density="compact"
                        hide-details
                        style="max-width:200px"
                        label="Статус"
                    />

                    <v-menu location="bottom">
                        <template #activator="{ props: menuProps }">
                            <v-btn
                                v-bind="menuProps"
                                size="small"
                                variant="tonal"
                                prepend-icon="mdi-plus"
                                :disabled="!addOptions.length"
                            >
                                Добавить
                            </v-btn>
                        </template>
                        <v-list density="compact" min-width="200">
                            <v-list-item
                                v-for="opt in addOptions"
                                :key="opt.id"
                                :prepend-icon="opt.icon"
                                :title="opt.label"
                                @click="onAdd(opt.id)"
                            />
                        </v-list>
                    </v-menu>
                </div>

                <!-- Optional fields -->
                <div v-if="showDeadline" class="d-flex align-center ga-2 mb-3">
                    <v-text-field
                        v-model="form.deadline"
                        type="datetime-local"
                        label="Дедлайн"
                        density="compact"
                        hide-details
                        class="flex-grow-1"
                    />
                    <v-btn icon variant="text" size="small" @click="clearDeadline">
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                </div>

                <v-select
                    v-if="showPriority"
                    v-model="form.priority_id"
                    :items="priorityItems"
                    item-title="label"
                    item-value="id"
                    label="Приоритет"
                    density="compact"
                    class="mb-3"
                />

                <v-select
                    v-if="showType"
                    v-model="form.type_id"
                    :items="typeItems"
                    item-title="label"
                    item-value="id"
                    label="Тип"
                    density="compact"
                    class="mb-3"
                />

                <v-textarea
                    v-if="showNote"
                    v-model="form.note"
                    label="Заметка"
                    rows="2"
                    auto-grow
                    class="mb-3"
                />

                <!-- Subtasks: only if any -->
                <template v-if="children.length">
                    <div class="text-caption font-weight-bold text-medium-emphasis mb-2">Подзадачи</div>
                    <div
                        v-for="child in children"
                        :key="child.id"
                        class="d-flex align-center ga-2 px-3 py-2 mb-1 skydesk-accent-panel"
                        style="cursor:pointer"
                        @click="emit('open-task', child.id)"
                    >
                        <v-icon size="16">mdi-subdirectory-arrow-right</v-icon>
                        <div class="flex-grow-1 text-body-2 font-weight-medium">{{ child.title }}</div>
                        <v-chip size="x-small" variant="tonal">
                            {{ store.getStatus(child.status_id)?.label }}
                        </v-chip>
                    </div>
                </template>

                <!-- Events: only if any or adding -->
                <template v-if="linkedEvents.length || showAttachEvent">
                    <div class="text-caption font-weight-bold text-medium-emphasis mb-2 mt-3">События</div>
                    <div
                        v-for="ev in linkedEvents"
                        :key="ev.id"
                        class="d-flex align-center ga-2 px-3 py-2 mb-1 skydesk-accent-panel"
                        style="cursor:pointer"
                        @click="emit('open-event', ev.id)"
                    >
                        <v-icon size="16">mdi-calendar</v-icon>
                        <div class="flex-grow-1 text-body-2">{{ ev.title }}</div>
                        <v-btn icon size="x-small" variant="text" @click.stop="detachEvent(ev.id)">
                            <v-icon size="16">mdi-link-off</v-icon>
                        </v-btn>
                    </div>

                    <div v-if="showAttachEvent && availableEvents.length" class="d-flex ga-2 mb-2 align-center">
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
                        <v-btn color="primary" variant="tonal" size="small" :disabled="!linkEventId" @click="attachEvent">
                            OK
                        </v-btn>
                    </div>

                    <v-card v-if="showNewEvent" variant="outlined" class="pa-4 mb-2">
                        <v-text-field v-model="newEvent.title" label="Название события" density="compact" hide-details class="mb-2" />
                        <v-row dense>
                            <v-col cols="6">
                                <v-select
                                    v-model="newEvent.type_id"
                                    :items="eventTypeItems"
                                    item-title="label"
                                    item-value="id"
                                    label="Тип"
                                    density="compact"
                                    hide-details
                                />
                            </v-col>
                            <v-col cols="6">
                                <v-text-field v-model="newEvent.start" type="datetime-local" label="Когда" density="compact" hide-details />
                            </v-col>
                        </v-row>
                        <div class="d-flex ga-2 mt-3">
                            <v-btn size="small" variant="tonal" @click="showNewEvent = false; showAttachEvent = false">
                                Отмена
                            </v-btn>
                            <v-btn color="primary" size="small" @click="createAndAttachEvent">Создать</v-btn>
                        </div>
                    </v-card>
                </template>

                <!-- Advances: only if any -->
                <template v-if="linkedAdvances.length">
                    <div class="text-caption font-weight-bold text-medium-emphasis mb-2 mt-3">Авансы</div>
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
                </template>

                <template v-if="attachments.length || uploading">
                    <div class="text-caption font-weight-bold text-medium-emphasis mb-2 mt-3">Вложения</div>
                    <div
                        v-for="att in attachments"
                        :key="att.id"
                        class="d-flex align-center ga-2 px-3 py-2 mb-1 skydesk-accent-panel"
                    >
                        <v-icon size="16">{{ att.kind === 'image' ? 'mdi-image-outline' : 'mdi-file-outline' }}</v-icon>
                        <a
                            :href="att.url"
                            target="_blank"
                            rel="noopener"
                            class="flex-grow-1 text-body-2 text-decoration-none"
                        >{{ att.original_name }}</a>
                        <v-btn icon size="x-small" variant="text" @click="removeAttachment(att.id)">
                            <v-icon size="16">mdi-delete-outline</v-icon>
                        </v-btn>
                    </div>
                    <div v-if="uploading" class="text-caption text-medium-emphasis">Загрузка…</div>
                </template>

                <input
                    ref="fileInput"
                    type="file"
                    class="d-none"
                    multiple
                    accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt"
                    @change="onFilesSelected"
                />
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
