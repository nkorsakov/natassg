<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { useDisplay } from 'vuetify';
import { usePage } from '@inertiajs/vue3';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';
import { useIsAdmin } from '@/composables/useIsAdmin';
import { prepareUploadFile } from '@/utils/compressImage';
import { dictChipStyle, dictDotStyle } from '@/utils/dictColor';
import { formatDisplayDate } from '@/utils/datetime';
import { linkifyParts } from '@/utils/linkify';
import DateTimeFields from '@/Components/DateTimeFields.vue';
import OwnerBadge from '@/Components/OwnerBadge.vue';

const model = defineModel({ type: Boolean, default: false });
const props = defineProps({
    taskId: { type: [String, Number], default: null },
});
const emit = defineEmits(['open-task', 'open-event', 'open-advance', 'open-advance-create']);

const { mdAndUp } = useDisplay();
const page = usePage();
const store = useSkyDeskStore();
const { isAdmin } = useIsAdmin();

const authUserId = computed(() => page.props.auth?.user?.id ?? null);

const confirmClose = ref(false);
const confirmDelete = ref(false);
const linkEventId = ref(null);
const showNewEvent = ref(false);
const showDeadline = ref(false);
const showPriority = ref(false);
const showNote = ref(false);
const showAttachEvent = ref(false);
const showPickEvent = ref(false);
const showReminderForm = ref(false);
const uploading = ref(false);
const fileInput = ref(null);
const editingTitle = ref(false);
const titleInput = ref(null);
const previewAttachment = ref(null);
const newReminderAt = ref('');
const newReminderMessage = ref('');
const commentDraft = ref('');
const editingCommentId = ref(null);
const editingCommentBody = ref('');

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
    status_id: store.defaultTaskStatusId(),
    priority_id: 'normal',
    type_id: 'purchase',
    deadline: '',
    note: '',
});

const syncForm = async () => {
    if (!task.value) return;
    const draftTitle = !String(task.value.title || '').trim()
        || ['Новое поручение', 'Новая подзадача'].includes(task.value.title);
    form.title = draftTitle ? '' : task.value.title;
    form.status_id = task.value.status_id;
    form.priority_id = task.value.priority_id;
    form.type_id = task.value.type_id;
    form.deadline = task.value.deadline ? String(task.value.deadline).slice(0, 16) : '';
    form.note = task.value.note || '';
    showDeadline.value = !!task.value.deadline;
    showPriority.value = task.value.priority_id !== 'normal';
    showNote.value = !!task.value.note;
    showNewEvent.value = false;
    showAttachEvent.value = false;
    showReminderForm.value = false;
    newReminderAt.value = '';
    newReminderMessage.value = '';
    commentDraft.value = '';
    editingCommentId.value = null;
    editingCommentBody.value = '';
    linkEventId.value = null;
    editingTitle.value = false;
    if (draftTitle) {
        await startEditTitle();
    }
};

const startEditTitle = async () => {
    editingTitle.value = true;
    await nextTick();
    const el = titleInput.value?.$el?.querySelector?.('textarea') || titleInput.value?.$el;
    el?.focus?.();
};

const stopEditTitle = () => {
    editingTitle.value = false;
};

const titleParts = computed(() => linkifyParts(form.title || 'Без названия'));

watch(
    () => [model.value, props.taskId],
    () => {
        if (model.value && task.value) syncForm();
    },
    { immediate: true },
);

const children = computed(() => (props.taskId ? store.childrenOf(props.taskId) : []));

const eventDateBadge = (ev) => {
    const s = String(ev?.start || '');
    const [y, m, d] = s.slice(0, 10).split('-').map(Number);
    if (!y || !m || !d) return null;
    const months = ['янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
    return {
        day: String(d),
        month: months[m - 1],
        time: ev.allDay ? '' : s.slice(11, 16),
    };
};

const linkedEvents = computed(() =>
    (props.taskId ? store.eventsForTask(props.taskId) : []).map((ev) => ({
        ...ev,
        badge: eventDateBadge(ev),
        typeLabel: store.getEventType(ev.type_id)?.label || 'Календарь',
        typeColor: store.getEventType(ev.type_id)?.color || null,
    })),
);
const linkedAdvances = computed(() => (props.taskId ? store.advancesForTask(props.taskId) : []));
const availableEvents = computed(() =>
    store.events.value
        .filter((e) => !task.value?.event_ids?.includes(e.id))
        .map((e) => ({
            ...e,
            label: [
                isAdmin.value && e.user?.initials ? e.user.initials : null,
                formatDisplayDate(e.start, { allDay: e.allDay }) || 'без даты',
                e.title,
            ].filter(Boolean).join(' · '),
        })),
);

const statusItems = computed(() => store.dictionaries.value.statuses);
const priorityItems = computed(() => store.dictionaries.value.priorities);
const typeItems = computed(() => store.dictionaries.value.taskTypes);
const eventTypeItems = computed(() => store.dictionaries.value.eventTypes);

const addOptions = computed(() => {
    const opts = [];
    if (!showDeadline.value) opts.push({ id: 'deadline', label: 'Дедлайн', icon: 'mdi-clock-outline' });
    if (!showPriority.value) opts.push({ id: 'priority', label: 'Приоритет', icon: 'mdi-flag-outline' });
    if (!showNote.value) opts.push({ id: 'note', label: 'Заметка', icon: 'mdi-note-text-outline' });
    opts.push({ id: 'reminder', label: 'Напоминание', icon: 'mdi-bell-outline' });
    opts.push({ id: 'subtask', label: 'Подзадача', icon: 'mdi-file-tree-outline' });
    opts.push({ id: 'event', label: 'Событие', icon: 'mdi-calendar-plus' });
    opts.push({ id: 'advance', label: 'Аванс', icon: 'mdi-cash-plus' });
    opts.push({ id: 'file', label: 'Файл / фото', icon: 'mdi-paperclip' });
    return opts;
});

const reminders = computed(() => task.value?.reminders || []);
const autoReminder = computed(() => reminders.value.find((r) => r.kind === 'deadline_auto') || null);
const manualReminders = computed(() => reminders.value.filter((r) => r.kind === 'manual'));
const comments = computed(() => task.value?.comments || []);

const isOwnComment = (comment) => String(comment?.user_id) === String(authUserId.value);

const submitComment = () => {
    if (!props.taskId || !commentDraft.value.trim()) return;
    store.createTaskComment(props.taskId, commentDraft.value);
    commentDraft.value = '';
};

const onCommentDraftKeydown = (e) => {
    if (e.key !== 'Enter' || e.shiftKey) return;
    e.preventDefault();
    submitComment();
};

const startEditComment = (comment) => {
    editingCommentId.value = comment.id;
    editingCommentBody.value = comment.body || '';
};

const cancelEditComment = () => {
    editingCommentId.value = null;
    editingCommentBody.value = '';
};

const saveEditComment = () => {
    if (!props.taskId || !editingCommentId.value) return;
    const text = editingCommentBody.value.trim();
    if (!text) return;
    store.updateTaskComment(props.taskId, editingCommentId.value, text);
    cancelEditComment();
};

const onEditCommentKeydown = (e) => {
    if (e.key !== 'Enter' || e.shiftKey) return;
    e.preventDefault();
    saveEditComment();
};

const attachments = computed(() => task.value?.attachments || []);

const save = () => {
    if (!props.taskId || !task.value) return;
    store.updateTask(props.taskId, {
        title: form.title.trim(),
        status_id: form.status_id,
        priority_id: form.priority_id,
        type_id: form.type_id,
        deadline: form.deadline || null,
        note: form.note,
    });
};

const addManualReminder = () => {
    if (!props.taskId || !newReminderAt.value) return;
    store.createTaskReminder(props.taskId, {
        remind_at: newReminderAt.value,
        message: newReminderMessage.value.trim() || null,
    });
    showReminderForm.value = false;
    newReminderAt.value = '';
    newReminderMessage.value = '';
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
    } else if (id === 'note') {
        showNote.value = true;
    } else if (id === 'reminder') {
        showReminderForm.value = true;
        if (!newReminderAt.value) {
            const d = new Date();
            d.setHours(d.getHours() + 1, 0, 0, 0);
            newReminderAt.value = d.toISOString().slice(0, 16);
        }
    } else if (id === 'subtask') {
        const child = await store.createTask({
            title: '',
            parent_id: props.taskId,
        });
        if (child?.id) emit('open-task', child.id);
    } else if (id === 'event') {
        showAttachEvent.value = true;
        showPickEvent.value = false;
        showNewEvent.value = false;
        linkEventId.value = null;
    } else if (id === 'advance') {
        emit('open-advance-create', { task_id: props.taskId });
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

const openAttachment = (att) => {
    if (att.kind === 'image') {
        previewAttachment.value = att;
        return;
    }
    window.open(att.url, '_blank', 'noopener,noreferrer');
};

const closePreview = () => {
    previewAttachment.value = null;
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

const requestDelete = () => {
    confirmDelete.value = true;
};

const confirmCascadeDelete = () => {
    if (!props.taskId) return;
    store.removeTask(props.taskId);
    confirmDelete.value = false;
    model.value = false;
};

const attachEvent = () => {
    if (!linkEventId.value) return;
    store.linkTaskEvent(props.taskId, linkEventId.value);
    linkEventId.value = null;
    showPickEvent.value = false;
};

const openCreateEventModal = () => {
    newEvent.title = '';
    newEvent.type_id = 'meeting';
    newEvent.allDay = false;
    if (form.deadline) {
        newEvent.start = form.deadline.includes('T')
            ? form.deadline.slice(0, 16)
            : `${form.deadline.slice(0, 10)}T10:00`;
    } else {
        const d = new Date();
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        newEvent.start = `${y}-${m}-${day}T10:00`;
    }
    showPickEvent.value = false;
    showNewEvent.value = true;
};

const createAndAttachEvent = async () => {
    if (!newEvent.title.trim()) return;
    await store.createEvent({
        ...newEvent,
        title: newEvent.title.trim(),
        task_ids: [props.taskId],
    });
    showNewEvent.value = false;
    showAttachEvent.value = true;
    newEvent.title = '';
};

const detachEvent = (eventId) => {
    store.unlinkTaskEvent(props.taskId, eventId);
};

const clearDeadline = () => {
    form.deadline = '';
    showDeadline.value = false;
};

const isDone = computed(
    () => form.status_id === 'done' || task.value?.status_id === 'done',
);
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
                <div class="d-flex align-center ga-2 min-w-0">
                    <button
                        type="button"
                        class="skydesk-complete-check"
                        :class="{ 'skydesk-complete-check--done': isDone }"
                        :aria-label="isDone ? 'Поручение выполнено' : 'Отметить поручение выполненным'"
                        :disabled="isDone"
                        @click="requestClose"
                    >
                        <v-icon
                            :icon="isDone ? 'mdi-check-circle' : 'mdi-checkbox-blank-circle-outline'"
                            size="28"
                        />
                    </button>
                    <button
                        v-if="!isDone"
                        type="button"
                        class="skydesk-complete-label"
                        @click="requestClose"
                    >
                        Отметить выполненным
                    </button>
                    <span v-else class="skydesk-complete-label skydesk-complete-label--done">
                        Поручение выполнено
                    </span>
                    <OwnerBadge :show="isAdmin" :user="task.user" />
                </div>
                <div class="d-flex ga-2 flex-shrink-0">
                    <v-btn
                        v-if="task.parent_id"
                        size="small"
                        variant="tonal"
                        @click="promote"
                    >
                        Сделать основной
                    </v-btn>
                    <v-btn icon variant="tonal" size="small" aria-label="Закрыть" @click="model = false">
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                </div>
            </v-card-title>
            <v-divider />

            <v-card-text class="px-6 py-5 flex-grow-1" style="overflow:auto">
                <div v-if="!editingTitle" class="mb-4 skydesk-title-read" @click="startEditTitle">
                    <div class="text-caption text-medium-emphasis mb-1">Что сделать</div>
                    <div class="text-h6 font-weight-bold" style="white-space:pre-wrap;word-break:break-word;line-height:1.35">
                        <template v-for="(part, idx) in titleParts" :key="idx">
                            <a
                                v-if="part.type === 'link'"
                                :href="part.href"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="skydesk-title-link"
                                @click.stop
                            >{{ part.value }}</a>
                            <template v-else>{{ part.value }}</template>
                        </template>
                    </div>
                </div>
                <v-textarea
                    v-else
                    ref="titleInput"
                    v-model="form.title"
                    label="Что сделать"
                    rows="2"
                    auto-grow
                    max-rows="8"
                    hide-details
                    class="mb-4"
                    autofocus
                    @blur="stopEditTitle"
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
                    >
                        <template #selection>
                            <span class="d-inline-flex align-center ga-2">
                                <span
                                    style="width:8px;height:8px;border-radius:50%;display:inline-block;flex-shrink:0"
                                    :style="dictDotStyle(store.getStatus(form.status_id)?.color)"
                                />
                                <span>{{ store.getStatus(form.status_id)?.label }}</span>
                            </span>
                        </template>
                    </v-select>

                    <v-select
                        v-model="form.type_id"
                        :items="typeItems"
                        item-title="label"
                        item-value="id"
                        density="compact"
                        hide-details
                        style="max-width:200px"
                        label="Тип"
                    >
                        <template #selection>
                            <span class="d-inline-flex align-center ga-2">
                                <v-icon
                                    size="16"
                                    :icon="store.getTaskType(form.type_id)?.icon || 'mdi-checkbox-blank-circle-outline'"
                                    :style="{ color: store.getTaskType(form.type_id)?.color || undefined }"
                                />
                                <span>{{ store.getTaskType(form.type_id)?.label }}</span>
                            </span>
                        </template>
                    </v-select>

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
                    <DateTimeFields
                        v-model="form.deadline"
                        class="flex-grow-1"
                        density="compact"
                        hide-details
                        date-label="Дедлайн · дата"
                        time-label="Время"
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
                >
                    <template #selection>
                        <span class="d-inline-flex align-center ga-2">
                            <span
                                style="width:8px;height:8px;border-radius:50%;display:inline-block;flex-shrink:0"
                                :style="dictDotStyle(store.getPriority(form.priority_id)?.color)"
                            />
                            <span>{{ store.getPriority(form.priority_id)?.label }}</span>
                        </span>
                    </template>
                </v-select>

                <v-textarea
                    v-if="showNote"
                    v-model="form.note"
                    label="Заметка"
                    rows="2"
                    auto-grow
                    class="mb-3"
                />

                <div class="mb-3">
                    <div class="text-caption font-weight-medium text-medium-emphasis mb-1">
                        Комментарии
                        <span v-if="comments.length" class="text-disabled">· {{ comments.length }}</span>
                    </div>

                    <div class="d-flex align-end ga-2 mb-2">
                        <v-textarea
                            v-model="commentDraft"
                            placeholder="Написать… Enter — отправить"
                            rows="1"
                            max-rows="6"
                            auto-grow
                            density="compact"
                            hide-details
                            variant="outlined"
                            class="flex-grow-1"
                            @keydown="onCommentDraftKeydown"
                        />
                        <v-btn
                            icon
                            size="small"
                            color="primary"
                            variant="tonal"
                            :disabled="!commentDraft.trim()"
                            aria-label="Отправить"
                            @click="submitComment"
                        >
                            <v-icon size="18">mdi-send</v-icon>
                        </v-btn>
                    </div>

                    <div
                        v-for="c in comments"
                        :key="c.id"
                        class="py-2"
                    >
                        <div v-if="editingCommentId === c.id">
                            <v-textarea
                                v-model="editingCommentBody"
                                rows="1"
                                max-rows="6"
                                auto-grow
                                density="compact"
                                hide-details
                                variant="outlined"
                                autofocus
                                @keydown="onEditCommentKeydown"
                                @keydown.esc.prevent="cancelEditComment"
                            />
                            <div class="d-flex justify-end ga-1 mt-1">
                                <v-btn size="x-small" variant="text" @click="cancelEditComment">Отмена</v-btn>
                                <v-btn
                                    size="x-small"
                                    color="primary"
                                    variant="text"
                                    :disabled="!editingCommentBody.trim()"
                                    @click="saveEditComment"
                                >
                                    Сохранить
                                </v-btn>
                            </div>
                        </div>
                        <template v-else>
                            <div
                                class="text-body-2"
                                :style="{
                                    whiteSpace: 'pre-wrap',
                                    wordBreak: 'break-word',
                                    lineHeight: 1.4,
                                    cursor: isOwnComment(c) ? 'pointer' : undefined,
                                }"
                                @click="isOwnComment(c) && startEditComment(c)"
                            >
                                <template v-for="(part, idx) in linkifyParts(c.body)" :key="idx">
                                    <a
                                        v-if="part.type === 'link'"
                                        :href="part.href"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        @click.stop
                                    >{{ part.value }}</a>
                                    <template v-else>{{ part.value }}</template>
                                </template>
                            </div>
                            <div class="d-flex align-center ga-1" style="margin-top:2px;min-height:18px">
                                <span
                                    class="text-disabled"
                                    style="font-size:11px;line-height:1.2"
                                >
                                    {{ c.user?.name || 'Пользователь' }}
                                    · {{ formatDisplayDate(c.created_at) || c.created_at }}
                                    <template v-if="c.updated_at && c.updated_at !== c.created_at"> · изм.</template>
                                </span>
                                <v-btn
                                    v-if="isOwnComment(c)"
                                    icon
                                    variant="text"
                                    size="x-small"
                                    density="compact"
                                    class="ml-auto"
                                    aria-label="Удалить"
                                    @click="store.removeTaskComment(props.taskId, c.id)"
                                >
                                    <v-icon size="14">mdi-close</v-icon>
                                </v-btn>
                            </div>
                        </template>
                    </div>
                </div>

                <div
                    v-if="autoReminder || manualReminders.length || showReminderForm"
                    class="mb-3 pa-3 skydesk-accent-panel"
                >
                    <div class="d-flex align-center ga-2 mb-2">
                        <v-icon size="18" color="primary">mdi-bell-outline</v-icon>
                        <div class="text-caption font-weight-bold text-medium-emphasis">Напоминания</div>
                    </div>

                    <div
                        v-if="autoReminder"
                        class="text-body-2 mb-2 d-flex align-center justify-space-between ga-2"
                    >
                        <span>
                            Авто · за 2 ч до дедлайна
                            <span class="text-medium-emphasis">
                                · {{ formatDisplayDate(autoReminder.remind_at) || autoReminder.remind_at }}
                            </span>
                        </span>
                        <v-chip size="x-small" variant="tonal">авто</v-chip>
                    </div>

                    <div
                        v-for="rem in manualReminders"
                        :key="rem.id"
                        class="d-flex align-center justify-space-between ga-2 mb-1"
                    >
                        <div class="text-body-2">
                            {{ formatDisplayDate(rem.remind_at) || rem.remind_at }}
                            <span v-if="rem.message" class="text-medium-emphasis"> · {{ rem.message }}</span>
                        </div>
                        <v-btn
                            icon
                            variant="text"
                            size="x-small"
                            aria-label="Удалить напоминание"
                            @click="store.removeTaskReminder(props.taskId, rem.id)"
                        >
                            <v-icon size="16">mdi-close</v-icon>
                        </v-btn>
                    </div>

                    <div v-if="showReminderForm" class="mt-2">
                        <DateTimeFields
                            v-model="newReminderAt"
                            class="mb-2"
                            density="compact"
                            hide-details
                            date-label="Когда напомнить"
                            time-label="Время"
                        />
                        <v-text-field
                            v-model="newReminderMessage"
                            label="Текст (необязательно)"
                            density="compact"
                            hide-details
                            class="mb-2"
                        />
                        <div class="d-flex ga-2">
                            <v-btn
                                color="primary"
                                size="small"
                                :disabled="!newReminderAt"
                                @click="addManualReminder"
                            >
                                Сохранить
                            </v-btn>
                            <v-btn size="small" variant="text" @click="showReminderForm = false">
                                Отмена
                            </v-btn>
                        </div>
                    </div>
                </div>

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
                        <v-chip
                            size="x-small"
                            variant="tonal"
                            class="skydesk-pill"
                            :style="dictChipStyle(store.getStatus(child.status_id)?.color)"
                        >
                            {{ store.getStatus(child.status_id)?.label }}
                        </v-chip>
                    </div>
                </template>

                <!-- Events: only if any or adding -->
                <template v-if="linkedEvents.length || showAttachEvent">
                    <div class="skydesk-calendar-block mt-3 mb-1">
                        <div class="d-flex align-center ga-2 mb-2">
                            <v-icon size="18" color="primary">mdi-calendar-month-outline</v-icon>
                            <div class="text-caption font-weight-bold text-medium-emphasis">Событие из календаря</div>
                        </div>

                        <div
                            v-for="ev in linkedEvents"
                            :key="ev.id"
                            class="d-flex align-center ga-3 px-3 py-2 mb-1 skydesk-calendar-row"
                            style="cursor:pointer"
                            @click="emit('open-event', ev.id)"
                        >
                            <div
                                v-if="ev.badge"
                                class="skydesk-cal-badge"
                                :style="ev.typeColor ? { borderColor: ev.typeColor } : undefined"
                            >
                                <div class="skydesk-cal-badge__day">{{ ev.badge.day }}</div>
                                <div
                                    class="skydesk-cal-badge__month"
                                    :style="ev.typeColor ? { background: ev.typeColor } : undefined"
                                >{{ ev.badge.month }}</div>
                            </div>
                            <v-icon v-else size="20" color="primary">mdi-calendar</v-icon>
                            <div class="flex-grow-1 min-w-0">
                                <div class="text-body-2 font-weight-medium text-truncate">{{ ev.title }}</div>
                                <div class="text-caption text-medium-emphasis">
                                    <template v-if="ev.badge?.time">{{ ev.badge.time }} · </template>
                                    {{ ev.typeLabel }}
                                </div>
                            </div>
                            <v-btn icon size="x-small" variant="text" @click.stop="detachEvent(ev.id)">
                                <v-icon size="16">mdi-link-off</v-icon>
                            </v-btn>
                        </div>

                        <div v-if="showAttachEvent" class="d-flex flex-wrap ga-2 mb-1">
                            <v-btn
                                size="small"
                                variant="tonal"
                                prepend-icon="mdi-calendar-search"
                                :disabled="!availableEvents.length"
                                @click="showPickEvent = !showPickEvent"
                            >
                                Выбрать
                            </v-btn>
                            <v-btn
                                size="small"
                                color="primary"
                                variant="tonal"
                                prepend-icon="mdi-calendar-plus"
                                @click="openCreateEventModal"
                            >
                                Создать
                            </v-btn>
                        </div>

                        <div v-if="showAttachEvent && showPickEvent && availableEvents.length" class="d-flex ga-2 mb-1 align-center">
                            <v-select
                                v-model="linkEventId"
                                :items="availableEvents"
                                item-title="label"
                                item-value="id"
                                label="Событие из календаря"
                                density="compact"
                                hide-details
                                prepend-inner-icon="mdi-calendar-search"
                                class="flex-grow-1"
                            />
                            <v-btn color="primary" variant="tonal" size="small" :disabled="!linkEventId" @click="attachEvent">
                                OK
                            </v-btn>
                        </div>

                        <div
                            v-if="showAttachEvent && !availableEvents.length"
                            class="text-caption text-medium-emphasis mb-1"
                        >
                            В календаре пока нет свободных событий — можно создать новое.
                        </div>
                    </div>
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
                                {{ store.formatMoney(adv.amount) }} ·
                                <span :style="{ color: store.getAdvanceStatus(adv.status_id)?.color }">
                                    {{ store.getAdvanceStatus(adv.status_id)?.label }}
                                </span>
                            </div>
                        </div>
                        <v-icon size="18">mdi-chevron-right</v-icon>
                    </div>
                </template>

                <template v-if="attachments.length || uploading">
                    <div class="text-caption font-weight-bold text-medium-emphasis mb-2 mt-3">Вложения</div>
                    <div class="d-flex flex-wrap ga-2 mb-1">
                        <div
                            v-for="att in attachments.filter((a) => a.kind === 'image')"
                            :key="att.id"
                            class="skydesk-att-item"
                        >
                            <button
                                type="button"
                                class="skydesk-att-thumb-btn"
                                aria-label="Открыть фото"
                                @click="openAttachment(att)"
                            >
                                <img
                                    :src="att.url"
                                    alt=""
                                    class="skydesk-att-thumb"
                                    loading="lazy"
                                >
                            </button>
                            <v-btn
                                class="skydesk-att-remove"
                                icon
                                size="x-small"
                                variant="flat"
                                aria-label="Удалить"
                                @click="removeAttachment(att.id)"
                            >
                                <v-icon size="14">mdi-close</v-icon>
                            </v-btn>
                        </div>
                    </div>

                    <div
                        v-for="att in attachments.filter((a) => a.kind !== 'image')"
                        :key="att.id"
                        class="d-flex align-center ga-2 px-3 py-2 mb-1 skydesk-accent-panel"
                    >
                        <v-icon size="16">mdi-file-outline</v-icon>
                        <button
                            type="button"
                            class="flex-grow-1 text-start text-body-2 skydesk-att-name"
                            @click="openAttachment(att)"
                        >
                            {{ att.original_name }}
                        </button>
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

            <v-divider />
            <v-card-actions class="px-6 py-4 flex-wrap ga-2">
                <v-btn variant="text" color="error" @click="requestDelete">
                    Удалить
                </v-btn>
                <v-spacer />
                <div class="text-caption text-medium-emphasis me-2">
                    Изменения сохраняются сразу
                </div>
                <v-btn variant="text" @click="model = false">
                    Закрыть окно
                </v-btn>
            </v-card-actions>
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

    <v-dialog v-model="confirmDelete" max-width="420">
        <v-card class="pa-5">
            <div class="text-h6 font-weight-bold mb-2">Удалить поручение?</div>
            <p class="text-body-2 text-medium-emphasis mb-4">
                {{
                    (children.length || store.descendantsOf(props.taskId).length)
                        ? 'Поручение и все вложенные подзадачи будут удалены. Авансы и события останутся, связь с ними снимется.'
                        : 'Поручение будет удалено. Авансы и события останутся, связь с ними снимется.'
                }}
            </p>
            <div class="d-flex justify-end ga-2">
                <v-btn variant="tonal" @click="confirmDelete = false">Отмена</v-btn>
                <v-btn color="error" @click="confirmCascadeDelete">Удалить</v-btn>
            </div>
        </v-card>
    </v-dialog>

    <v-dialog v-model="showNewEvent" max-width="440">
        <v-card class="pa-5">
            <div class="text-h6 font-weight-bold mb-4">Новое событие</div>
            <v-text-field v-model="newEvent.title" label="Название" class="mb-2" />
            <v-select
                v-model="newEvent.type_id"
                :items="eventTypeItems"
                item-title="label"
                item-value="id"
                label="Тип"
                class="mb-2"
            />
            <DateTimeFields
                v-model="newEvent.start"
                :all-day="newEvent.allDay"
                class="mb-2"
            />
            <v-switch v-model="newEvent.allDay" label="Весь день" class="mb-2" hide-details />
            <div class="d-flex justify-end ga-2 mt-2">
                <v-btn variant="tonal" @click="showNewEvent = false">Отмена</v-btn>
                <v-btn color="primary" :disabled="!newEvent.title.trim()" @click="createAndAttachEvent">
                    Создать
                </v-btn>
            </div>
        </v-card>
    </v-dialog>

    <v-dialog
        :model-value="!!previewAttachment"
        max-width="920"
        @update:model-value="(v) => { if (!v) closePreview() }"
    >
        <v-card v-if="previewAttachment" class="pa-3">
            <div class="d-flex align-center justify-end ga-1 mb-2">
                <v-btn
                    icon
                    size="small"
                    variant="tonal"
                    :href="previewAttachment.url"
                    target="_blank"
                    rel="noopener"
                    aria-label="Открыть оригинал"
                >
                    <v-icon size="18">mdi-open-in-new</v-icon>
                </v-btn>
                <v-btn icon size="small" variant="tonal" aria-label="Закрыть" @click="closePreview">
                    <v-icon size="18">mdi-close</v-icon>
                </v-btn>
            </div>
            <img
                :src="previewAttachment.url"
                alt=""
                class="skydesk-att-preview"
            >
        </v-card>
    </v-dialog>
</template>

<style scoped>
.skydesk-title-read {
    cursor: text;
    border-radius: 12px;
    padding: 12px 14px;
    border: 1px solid transparent;
    transition: background-color 160ms ease, border-color 160ms ease;
}

.skydesk-title-read:hover {
    background: rgba(var(--v-theme-on-surface), 0.04);
    border-color: rgba(var(--v-border-color), var(--v-border-opacity));
}

.skydesk-title-link {
    color: rgb(var(--v-theme-primary));
    text-decoration: underline;
    text-underline-offset: 2px;
    word-break: break-all;
}

.skydesk-title-link:hover {
    opacity: 0.85;
}

.skydesk-complete-check {
    flex: 0 0 auto;
    width: 36px;
    height: 36px;
    padding: 0;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: rgba(var(--v-theme-on-surface), 0.4);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: color 160ms ease, transform 160ms ease, background-color 160ms ease;
}

.skydesk-complete-check:hover:not(:disabled) {
    color: rgb(var(--v-theme-success));
    background: rgba(var(--v-theme-success), 0.1);
    transform: scale(1.06);
}

.skydesk-complete-check--done,
.skydesk-complete-check--done:disabled {
    color: rgb(var(--v-theme-success));
    cursor: default;
}

.skydesk-complete-label {
    border: 0;
    background: transparent;
    padding: 0;
    cursor: pointer;
    font: inherit;
    font-size: 1.05rem;
    font-weight: 700;
    line-height: 1.2;
    color: rgb(var(--v-theme-on-surface));
    text-align: left;
}

.skydesk-complete-label:hover {
    color: rgb(var(--v-theme-success));
}

.skydesk-complete-label--done {
    color: rgb(var(--v-theme-success));
    cursor: default;
}

.skydesk-calendar-block {
    padding: 10px 12px 8px;
    border-radius: 14px;
    background: rgba(var(--v-theme-primary), 0.05);
    border: 1px dashed rgba(var(--v-theme-primary), 0.28);
}

.skydesk-calendar-row {
    background: rgb(var(--v-theme-surface));
    border: 1px solid rgba(var(--v-theme-primary), 0.14);
    border-radius: 12px;
}

.skydesk-cal-badge {
    flex: 0 0 auto;
    width: 40px;
    border-radius: 9px;
    border: 1.5px solid rgb(var(--v-theme-primary));
    overflow: hidden;
    text-align: center;
    line-height: 1;
    background: rgb(var(--v-theme-surface));
}

.skydesk-cal-badge__day {
    font-size: 0.95rem;
    font-weight: 700;
    padding: 5px 0 3px;
    color: rgb(var(--v-theme-on-surface));
}

.skydesk-cal-badge__month {
    font-size: 0.62rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    padding: 3px 0 4px;
    color: #fff;
    background: rgb(var(--v-theme-primary));
}

.skydesk-att-thumb-btn {
    flex: 0 0 auto;
    padding: 0;
    border: 0;
    background: transparent;
    cursor: pointer;
    border-radius: 12px;
    overflow: hidden;
    line-height: 0;
}

.skydesk-att-item {
    position: relative;
    width: 72px;
    height: 72px;
}

.skydesk-att-thumb {
    width: 72px;
    height: 72px;
    object-fit: cover;
    display: block;
    border-radius: 12px;
    background: rgba(var(--v-theme-on-surface), 0.06);
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.skydesk-att-file {
    width: 72px;
    height: 72px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(var(--v-theme-on-surface), 0.06);
    color: rgba(var(--v-theme-on-surface), 0.55);
    cursor: pointer;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    padding: 0;
}

.skydesk-att-name {
    border: 0;
    background: transparent;
    padding: 0;
    color: rgb(var(--v-theme-on-surface));
    cursor: pointer;
    word-break: break-all;
}

.skydesk-att-name:hover {
    color: rgb(var(--v-theme-primary));
}

.skydesk-att-remove {
    position: absolute !important;
    top: 4px;
    right: 4px;
    width: 22px !important;
    height: 22px !important;
    min-width: 22px !important;
    background: rgba(25, 24, 39, 0.55) !important;
    color: #fff !important;
}

.skydesk-att-preview {
    display: block;
    width: 100%;
    max-height: min(78vh, 820px);
    object-fit: contain;
    border-radius: 12px;
    background: rgba(var(--v-theme-on-surface), 0.04);
}
</style>
