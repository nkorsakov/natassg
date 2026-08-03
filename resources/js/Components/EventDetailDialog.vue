<script setup>
import { computed, nextTick, ref, reactive, watch } from 'vue';
import { useDisplay } from 'vuetify';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';
import { useIsAdmin } from '@/composables/useIsAdmin';
import { dictChipStyle, dictDotStyle } from '@/utils/dictColor';
import { formatDisplayDate } from '@/utils/datetime';
import DateTimeFields from '@/Components/DateTimeFields.vue';
import OwnerBadge from '@/Components/OwnerBadge.vue';

const model = defineModel({ type: Boolean, default: false });
const props = defineProps({
    eventId: { type: [String, Number], default: null },
});
const emit = defineEmits(['open-task', 'create-task']);

const { mdAndUp } = useDisplay();
const store = useSkyDeskStore();
const { isAdmin } = useIsAdmin();

const linkTaskId = ref(null);
const showPickTask = ref(false);
const editingTitle = ref(false);
const titleInput = ref(null);

const event = computed(() => (props.eventId ? store.getEvent(props.eventId) : null));
const linkedTasks = computed(() => (props.eventId ? store.tasksForEvent(props.eventId) : []));
const eventTypeItems = computed(() => store.dictionaries.value.eventTypes);
const availableTasks = computed(() =>
    store.tasks.value
        .filter((t) => !event.value?.task_ids?.includes(t.id) && !['done', 'cancelled'].includes(t.status_id))
        .map((t) => ({
            ...t,
            label: [
                isAdmin.value && t.user?.initials ? t.user.initials : null,
                t.title,
                formatDisplayDate(t.deadline, { withTime: String(t.deadline || '').includes('T') }) || null,
            ].filter(Boolean).join(' · '),
        })),
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
        showPickTask.value = false;
        editingTitle.value = false;
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
    showPickTask.value = false;
};

const detachTask = (taskId) => {
    store.unlinkTaskEvent(taskId, props.eventId);
};

const createTask = () => {
    showPickTask.value = false;
    emit('create-task', props.eventId);
};

const startEditTitle = async () => {
    editingTitle.value = true;
    await nextTick();
    const el = titleInput.value?.$el?.querySelector?.('input') || titleInput.value?.$el;
    el?.focus?.();
};

const stopEditTitle = () => {
    editingTitle.value = false;
    if (!form.title.trim() && event.value?.title) {
        form.title = event.value.title;
    }
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
            <v-card-title class="d-flex align-center justify-space-between px-4 px-sm-5 pt-4 pb-3 ga-3">
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex align-center ga-2 mb-1" v-if="isAdmin && event.user">
                        <OwnerBadge :show="true" :user="event.user" />
                        <span class="text-caption text-medium-emphasis text-truncate">{{ event.user.name }}</span>
                    </div>
                    <div
                        v-if="!editingTitle"
                        class="skydesk-event-title-read"
                        @click="startEditTitle"
                    >
                        {{ form.title || 'Без названия' }}
                    </div>
                    <v-text-field
                        v-else
                        ref="titleInput"
                        v-model="form.title"
                        density="compact"
                        hide-details
                        autofocus
                        placeholder="Название события"
                        @blur="stopEditTitle"
                        @keydown.enter.prevent="stopEditTitle"
                    />
                </div>
                <v-btn icon variant="tonal" size="small" class="flex-shrink-0" @click="model = false">
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </v-card-title>
            <v-divider />
            <v-card-text class="px-4 px-sm-5 py-3 skydesk-event-form">
                <v-row dense>
                    <v-col cols="12" sm="6">
                        <v-select
                            v-model="form.type_id"
                            :items="eventTypeItems"
                            item-title="label"
                            item-value="id"
                            label="Тип"
                            density="compact"
                            hide-details
                        >
                            <template #selection>
                                <span class="d-inline-flex align-center ga-2">
                                    <span
                                        style="width:8px;height:8px;border-radius:50%;display:inline-block;flex-shrink:0"
                                        :style="dictDotStyle(store.getEventType(form.type_id)?.color)"
                                    />
                                    <span>{{ store.getEventType(form.type_id)?.label }}</span>
                                </span>
                            </template>
                        </v-select>
                    </v-col>
                    <v-col cols="12" sm="6">
                        <v-text-field
                            v-model="form.place"
                            label="Место"
                            density="compact"
                            hide-details
                        />
                    </v-col>
                    <v-col cols="12" sm="6">
                        <DateTimeFields
                            v-model="form.start"
                            :all-day="form.allDay"
                            density="compact"
                            hide-details
                            date-label="Начало"
                            time-label="Время"
                        />
                    </v-col>
                    <v-col cols="12" sm="6">
                        <DateTimeFields
                            v-model="form.end"
                            :all-day="form.allDay"
                            density="compact"
                            hide-details
                            date-label="Конец"
                            time-label="Время"
                        />
                    </v-col>
                    <v-col cols="12" class="d-flex align-center py-0">
                        <v-switch
                            v-model="form.allDay"
                            label="Весь день"
                            density="compact"
                            hide-details
                        />
                    </v-col>
                    <v-col cols="12">
                        <v-textarea
                            v-model="form.note"
                            label="Заметка"
                            rows="2"
                            auto-grow
                            max-rows="5"
                            density="compact"
                            hide-details
                        />
                    </v-col>
                </v-row>

                <div class="skydesk-task-link-block mt-3">
                    <div class="d-flex align-center ga-2 mb-1">
                        <v-icon size="16" color="primary">mdi-checkbox-marked-outline</v-icon>
                        <div class="text-caption font-weight-bold text-medium-emphasis">Поручения к событию</div>
                    </div>

                    <div
                        v-for="t in linkedTasks"
                        :key="t.id"
                        class="d-flex align-center ga-2 px-2 py-1 mb-1 skydesk-accent-panel"
                        style="cursor:pointer"
                        @click="emit('open-task', t.id)"
                    >
                        <div class="flex-grow-1 text-body-2 font-weight-medium text-truncate">{{ t.title }}</div>
                        <v-chip
                            size="x-small"
                            variant="tonal"
                            class="skydesk-pill"
                            :style="dictChipStyle(store.getStatus(t.status_id)?.color)"
                        >
                            {{ store.getStatus(t.status_id)?.label }}
                        </v-chip>
                        <v-btn icon size="x-small" variant="text" @click.stop="detachTask(t.id)">
                            <v-icon size="16">mdi-link-off</v-icon>
                        </v-btn>
                    </div>

                    <div v-if="!linkedTasks.length" class="text-caption text-medium-emphasis mb-1">
                        Пока ни одно поручение не привязано
                    </div>

                    <div class="d-flex flex-wrap ga-2">
                        <v-btn
                            size="small"
                            variant="tonal"
                            prepend-icon="mdi-link-variant"
                            :disabled="!availableTasks.length"
                            @click="showPickTask = !showPickTask"
                        >
                            Привязать
                        </v-btn>
                        <v-btn
                            size="small"
                            color="primary"
                            variant="tonal"
                            prepend-icon="mdi-plus"
                            @click="createTask"
                        >
                            Создать
                        </v-btn>
                    </div>

                    <div v-if="showPickTask && availableTasks.length" class="d-flex ga-2 mt-2 align-center">
                        <v-select
                            v-model="linkTaskId"
                            :items="availableTasks"
                            item-title="label"
                            item-value="id"
                            label="Выберите поручение"
                            density="compact"
                            hide-details
                            prepend-inner-icon="mdi-magnify"
                            class="flex-grow-1"
                        />
                        <v-btn color="primary" variant="tonal" size="small" :disabled="!linkTaskId" @click="attachTask">
                            OK
                        </v-btn>
                    </div>

                    <div
                        v-if="!availableTasks.length"
                        class="text-caption text-medium-emphasis mt-1"
                    >
                        Свободных поручений нет — можно создать новое.
                    </div>
                </div>
            </v-card-text>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.skydesk-event-title-read {
    font-size: 1.125rem;
    font-weight: 700;
    line-height: 1.3;
    cursor: text;
    border-radius: 10px;
    padding: 6px 10px;
    margin: -6px -10px;
    border: 1px solid transparent;
    word-break: break-word;
    transition: background-color 160ms ease, border-color 160ms ease;
}

.skydesk-event-title-read:hover {
    background: rgba(var(--v-theme-on-surface), 0.04);
    border-color: rgba(var(--v-border-color), var(--v-border-opacity));
}

.skydesk-event-form :deep(.v-col) {
    padding-top: 2px;
    padding-bottom: 2px;
}

.skydesk-event-form :deep(.v-row--dense > .v-col) {
    padding-top: 2px;
    padding-bottom: 2px;
}

.skydesk-task-link-block {
    padding: 8px 10px 6px;
    border-radius: 12px;
    background: rgba(var(--v-theme-primary), 0.05);
    border: 1px dashed rgba(var(--v-theme-primary), 0.28);
}
</style>
