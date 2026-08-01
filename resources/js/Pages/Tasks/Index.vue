<script setup>
import { computed, ref } from 'vue';
import { useDisplay } from 'vuetify';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';
import { useWorkspaceUi } from '@/composables/useWorkspaceUi';
import { dictChipStyle } from '@/utils/dictColor';

const { mdAndUp } = useDisplay();
const store = useSkyDeskStore();
const { openTask } = useWorkspaceUi();

const filter = ref('all');

const formatDeadline = (dl) => {
    if (!dl) return 'Без срока';
    const d = new Date(dl);
    return d.toLocaleString('ru-RU', {
        day: 'numeric',
        month: 'short',
        hour: String(dl).includes('T') ? '2-digit' : undefined,
        minute: String(dl).includes('T') ? '2-digit' : undefined,
    });
};

const filters = computed(() => {
    const roots = store.rootTasks.value;
    const today = new Date().toISOString().slice(0, 10);
    const statusColor = (slug) => store.getStatus(slug)?.color;
    return [
        { value: 'all', label: `Все · ${roots.length}`, color: null },
        {
            value: 'today',
            label: `Сегодня · ${roots.filter((t) => t.deadline?.startsWith(today)).length}`,
            color: null,
        },
        {
            value: 'in_progress',
            label: `В работе · ${roots.filter((t) => t.status_id === 'in_progress').length}`,
            color: statusColor('in_progress'),
        },
        {
            value: 'waiting_money',
            label: `Ждут денег · ${roots.filter((t) => t.status_id === 'waiting_money').length}`,
            color: statusColor('waiting_money'),
        },
        {
            value: 'done',
            label: `Готово · ${roots.filter((t) => t.status_id === 'done').length}`,
            color: statusColor('done'),
        },
    ];
});

const visibleTasks = computed(() => {
    let list = store.rootTasks.value;
    const today = new Date().toISOString().slice(0, 10);
    if (filter.value === 'today') {
        list = list.filter((t) => t.deadline?.startsWith(today));
    } else if (filter.value === 'in_progress') {
        list = list.filter((t) => t.status_id === 'in_progress');
    } else if (filter.value === 'waiting_money') {
        list = list.filter((t) => t.status_id === 'waiting_money');
    } else if (filter.value === 'done') {
        list = list.filter((t) => t.status_id === 'done');
    } else {
        list = list.filter((t) => t.status_id !== 'done' && t.status_id !== 'cancelled');
    }
    return list;
});

const weekProgress = computed(() => {
    const all = store.tasks.value;
    const done = all.filter((t) => t.status_id === 'done').length;
    const total = all.length || 1;
    return { done, total, percent: Math.round((done / total) * 100) };
});

const taskType = (task) => store.getTaskType(task.type_id);
const taskStatus = (task) => store.getStatus(task.status_id);
const taskPriority = (task) => store.getPriority(task.priority_id);

const childrenOf = (task) =>
    [...store.childrenOf(task.id)]
        .filter((c) => c.status_id !== 'cancelled')
        .sort((a, b) => {
            const ad = a.status_id === 'done' ? 1 : 0;
            const bd = b.status_id === 'done' ? 1 : 0;
            return ad - bd || String(a.title).localeCompare(String(b.title), 'ru');
        });

const isDone = (task) => task.status_id === 'done';

const completeSubtask = (child) => {
    if (isDone(child)) return;
    store.closeTaskCascade(child.id);
};

const filterChipStyle = (f) => {
    if (!f.color) return undefined;
    return dictChipStyle(f.color, filter.value === f.value ? 0.22 : 0.1);
};
</script>

<template>
    <AppLayout
        title="Поручения"
        subtitle="Всё, что нужно сделать — с подзадачами, сроками и авансами."
    >
        <div
            class="d-flex ga-2 mb-5"
            :style="mdAndUp ? '' : 'overflow-x:auto;margin:0 -16px;padding:0 16px 4px'"
        >
            <v-chip
                v-for="f in filters"
                :key="f.value"
                :color="filter === f.value && !f.color ? 'primary' : undefined"
                :variant="filter === f.value ? 'flat' : 'tonal'"
                class="flex-shrink-0"
                :style="filterChipStyle(f)"
                @click="filter = f.value"
            >
                {{ f.label }}
            </v-chip>
        </div>

        <v-row>
            <v-col cols="12" md="8">
                <v-card class="pa-2 d-flex flex-column ga-2 skydesk-task-list">
                    <div
                        v-for="task in visibleTasks"
                        :key="task.id"
                        class="skydesk-task"
                        :style="{ borderLeftColor: taskStatus(task)?.color || 'transparent' }"
                    >
                        <div
                            class="d-flex align-center ga-3 px-4 py-3"
                            style="cursor:pointer"
                            @click="openTask(task.id)"
                        >
                            <div
                                class="skydesk-stat-icon flex-shrink-0"
                                :style="{ background: (taskType(task)?.color || '#6957EE') + '22' }"
                            >
                                <v-icon
                                    size="18"
                                    :icon="taskType(task)?.icon || 'mdi-checkbox-blank-circle-outline'"
                                    :style="{ color: taskType(task)?.color || '#6957EE' }"
                                />
                            </div>
                            <div class="flex-grow-1 min-w-0 overflow-hidden">
                                <div class="text-body-2 font-weight-bold skydesk-task-title">{{ task.title }}</div>
                                <div class="text-caption text-medium-emphasis text-truncate">
                                    <template v-if="task.deadline">{{ formatDeadline(task.deadline) }}</template>
                                    <template v-if="task.deadline && taskType(task)?.label"> · </template>
                                    <template v-if="taskType(task)?.label">{{ taskType(task).label }}</template>
                                    <template v-if="!task.deadline && !taskType(task)?.label">
                                        {{ taskStatus(task)?.label }}
                                    </template>
                                </div>
                            </div>
                            <v-chip
                                v-if="task.priority_id && task.priority_id !== 'normal'"
                                size="small"
                                variant="tonal"
                                class="skydesk-pill flex-shrink-0"
                                :style="dictChipStyle(taskPriority(task)?.color)"
                            >
                                {{ taskPriority(task)?.label }}
                            </v-chip>
                            <v-chip
                                size="small"
                                variant="tonal"
                                class="skydesk-pill flex-shrink-0"
                                :style="dictChipStyle(taskStatus(task)?.color)"
                            >
                                {{ taskStatus(task)?.label }}
                            </v-chip>
                        </div>

                        <div v-if="childrenOf(task).length" class="skydesk-subtasks">
                            <div
                                v-for="child in childrenOf(task)"
                                :key="child.id"
                                class="skydesk-subtask"
                                :class="{ 'skydesk-subtask--done': isDone(child) }"
                            >
                                <button
                                    type="button"
                                    class="skydesk-subtask-check"
                                    :class="{ 'skydesk-subtask-check--done': isDone(child) }"
                                    :aria-label="isDone(child) ? 'Подзадача выполнена' : 'Отметить подзадачу выполненной'"
                                    :disabled="isDone(child)"
                                    @click.stop="completeSubtask(child)"
                                >
                                    <v-icon
                                        :icon="isDone(child) ? 'mdi-check-circle' : 'mdi-checkbox-blank-circle-outline'"
                                        size="16"
                                    />
                                </button>
                                <button
                                    type="button"
                                    class="skydesk-subtask-title"
                                    @click.stop="openTask(child.id)"
                                >
                                    {{ child.title }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-if="!visibleTasks.length" class="pa-8 text-center text-medium-emphasis">
                        Нет поручений в этом фильтре
                    </div>
                </v-card>
            </v-col>

            <v-col cols="12" md="4">
                <v-card class="pa-5 mb-4">
                    <div class="text-subtitle-2 font-weight-bold mb-2">Прогресс недели</div>
                    <v-progress-linear
                        :model-value="weekProgress.percent"
                        color="primary"
                        height="8"
                        rounded
                    />
                    <div class="d-flex justify-space-between text-caption text-medium-emphasis mt-2">
                        <span>{{ weekProgress.done }} из {{ weekProgress.total }}</span>
                        <b>{{ weekProgress.percent }}%</b>
                    </div>
                </v-card>
                <v-card class="pa-4 skydesk-accent-panel mb-4">
                    <div class="text-body-2 font-weight-bold mb-1">Подсказка</div>
                    <div class="text-caption text-medium-emphasis">
                        Откройте поручение, чтобы добавить подзадачи, событие или заявку на аванс.
                    </div>
                </v-card>
            </v-col>
        </v-row>
    </AppLayout>
</template>

<style scoped>
.skydesk-task-list {
    background: rgba(var(--v-theme-on-surface), 0.03);
}

.skydesk-task {
    overflow: hidden;
    border-radius: 12px;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-left: 3px solid transparent;
    background: rgb(var(--v-theme-surface));
    transition: border-color 160ms ease, box-shadow 160ms ease;
}

.skydesk-task:hover {
    border-color: rgba(var(--v-theme-primary), 0.28);
    box-shadow: 0 1px 2px rgba(25, 24, 39, 0.04);
}

.skydesk-task-title {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
    word-break: break-word;
    line-height: 1.35;
}

.skydesk-subtasks {
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    padding: 1px 8px 4px 14px;
}

.skydesk-subtask {
    display: flex;
    align-items: center;
    gap: 2px;
    padding: 0 2px;
    border-radius: 4px;
    min-height: 22px;
}

.skydesk-subtask:hover {
    background: rgba(var(--v-theme-on-surface), 0.04);
}

.skydesk-subtask-check {
    flex: 0 0 auto;
    width: 20px;
    height: 20px;
    margin-top: 0;
    padding: 0;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: rgba(var(--v-theme-on-surface), 0.38);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: color 160ms ease, background-color 160ms ease;
}

.skydesk-subtask-check:hover:not(:disabled) {
    color: rgb(var(--v-theme-success));
    background: rgba(var(--v-theme-success), 0.1);
}

.skydesk-subtask-check--done,
.skydesk-subtask-check--done:disabled {
    color: rgb(var(--v-theme-success));
    cursor: default;
}

.skydesk-subtask-title {
    flex: 1 1 auto;
    min-width: 0;
    border: 0;
    background: transparent;
    padding: 0;
    text-align: left;
    font: inherit;
    font-size: 0.75rem;
    line-height: 1.2;
    color: rgb(var(--v-theme-on-surface));
    cursor: pointer;
    word-break: break-word;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 1;
    overflow: hidden;
}

.skydesk-subtask--done .skydesk-subtask-title {
    color: rgba(var(--v-theme-on-surface), 0.45);
    text-decoration: line-through;
}
</style>
