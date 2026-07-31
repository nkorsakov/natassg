<script setup>
import { computed, ref } from 'vue';
import { useDisplay } from 'vuetify';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';
import { useWorkspaceUi } from '@/composables/useWorkspaceUi';

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
        hour: dl.includes('T') ? '2-digit' : undefined,
        minute: dl.includes('T') ? '2-digit' : undefined,
    });
};

const filters = computed(() => {
    const roots = store.rootTasks.value;
    const today = '2026-07-31';
    return [
        { value: 'all', label: `Все · ${roots.length}` },
        {
            value: 'today',
            label: `Сегодня · ${roots.filter((t) => t.deadline?.startsWith(today)).length}`,
        },
        {
            value: 'in_progress',
            label: `В работе · ${roots.filter((t) => t.status_id === 'in_progress').length}`,
        },
        {
            value: 'waiting_money',
            label: `Ждут денег · ${roots.filter((t) => t.status_id === 'waiting_money').length}`,
        },
        {
            value: 'done',
            label: `Готово · ${roots.filter((t) => t.status_id === 'done').length}`,
        },
    ];
});

const visibleTasks = computed(() => {
    let list = store.rootTasks.value;
    if (filter.value === 'today') {
        list = list.filter((t) => t.deadline?.startsWith('2026-07-31'));
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
                :color="filter === f.value ? 'primary' : undefined"
                :variant="filter === f.value ? 'flat' : 'tonal'"
                class="flex-shrink-0"
                @click="filter = f.value"
            >
                {{ f.label }}
            </v-chip>
        </div>

        <v-row>
            <v-col cols="12" md="8">
                <v-card>
                    <div
                        v-for="task in visibleTasks"
                        :key="task.id"
                        class="skydesk-task d-flex align-center ga-3 px-4 py-3"
                        style="border-radius:11px;min-height:56px;cursor:pointer"
                        @click="openTask(task.id)"
                    >
                        <div class="flex-grow-1 min-w-0">
                            <div class="text-body-2 font-weight-bold text-truncate">{{ task.title }}</div>
                            <div class="text-caption text-medium-emphasis text-truncate">
                                <template v-if="task.deadline">{{ formatDeadline(task.deadline) }}</template>
                                <template v-if="store.childrenOf(task.id).length">
                                    <span v-if="task.deadline"> · </span>
                                    {{ store.childrenOf(task.id).length }} подзадач
                                </template>
                                <template v-if="!task.deadline && !store.childrenOf(task.id).length">
                                    {{ store.getStatus(task.status_id)?.label }}
                                </template>
                            </div>
                        </div>
                        <v-chip
                            size="small"
                            variant="tonal"
                            class="skydesk-pill"
                        >
                            {{ store.getStatus(task.status_id)?.label }}
                        </v-chip>
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
