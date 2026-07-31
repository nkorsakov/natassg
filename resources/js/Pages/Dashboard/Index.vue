<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useDisplay } from 'vuetify';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';
import { useWorkspaceUi } from '@/composables/useWorkspaceUi';

const { mdAndUp } = useDisplay();
const store = useSkyDeskStore();
const { openTask, openEvent, openAdvance } = useWorkspaceUi();

const today = '2026-07-31';

const stats = computed(() => [
    {
        value: String(store.activeTaskCount.value),
        label: 'активных поручений',
        icon: 'mdi-check-circle-outline',
        bg: '#eceaff',
        color: 'primary',
    },
    {
        value: String(store.waitingMoneyCount.value),
        label: 'ожидают финансирования',
        icon: 'mdi-currency-rub',
        bg: '#fff1dd',
        color: 'warning',
    },
    {
        value: String(
            store.events.value.filter((e) => String(e.start).startsWith(today)).length,
        ),
        label: 'события на сегодня',
        icon: 'mdi-calendar-month-outline',
        bg: '#e2f7ee',
        color: 'success',
    },
    {
        value: String(
            store.advances.value.filter((a) => ['issued', 'reporting'].includes(a.status_id)).length,
        ),
        label: 'авансов на отчёте',
        icon: 'mdi-receipt-text-outline',
        bg: '#ffe9e9',
        color: 'error',
    },
]);

const priorityTasks = computed(() =>
    store.rootTasks.value
        .filter((t) => ['urgent', 'high'].includes(t.priority_id) && !['done', 'cancelled'].includes(t.status_id))
        .slice(0, 5),
);

const agenda = computed(() =>
    store.events.value
        .filter((e) => String(e.start).startsWith(today))
        .map((e) => ({
            id: e.id,
            time: e.allDay ? 'день' : String(e.start).slice(11, 16),
            title: e.title,
            desc: `${e.place || 'Без места'} · ${store.tasksForEvent(e.id).length} поруч.`,
            dot: store.getEventType(e.type_id)?.color || '#6957EE',
        })),
);

const financePreview = computed(() => {
    const pending = store.advances.value.find((a) => a.status_id === 'pending');
    if (!pending) return null;
    return {
        id: pending.id,
        label: 'Ожидает одобрения',
        count: `${store.pendingAdvanceCount.value} заявка`,
        amount: store.formatMoney(pending.amount),
        hint: pending.title,
    };
});
</script>

<template>
    <AppLayout
        :title="`Добрый день, ${store.profile.value.name.split(' ')[0]}`"
        subtitle="Вот что требует вашего внимания сегодня."
    >
        <div
            class="mb-6"
            :class="mdAndUp ? 'd-flex flex-wrap' : 'd-flex'"
            :style="mdAndUp
                ? 'gap:15px'
                : 'gap:10px;overflow-x:auto;margin:0 -16px;padding:0 16px 5px'"
        >
            <v-card
                v-for="stat in stats"
                :key="stat.label"
                class="pa-4"
                :style="mdAndUp
                    ? 'flex:1 1 0;min-width:180px;min-height:104px'
                    : 'flex:0 0 145px;min-height:111px'"
            >
                <div class="d-flex align-center ga-3 mb-3">
                    <div class="skydesk-stat-icon" :style="{ background: stat.bg }">
                        <v-icon :icon="stat.icon" size="18" :color="stat.color" />
                    </div>
                    <div class="text-h5 font-weight-bold" style="letter-spacing:-.5px">{{ stat.value }}</div>
                </div>
                <div class="text-caption text-medium-emphasis">{{ stat.label }}</div>
            </v-card>
        </div>

        <v-row>
            <v-col cols="12" :md="financePreview ? 8 : 12">
                <v-card>
                    <div class="d-flex align-center justify-space-between px-5 pt-5 pb-2">
                        <h2 class="text-subtitle-1 font-weight-bold mb-0">Приоритетные поручения</h2>
                        <v-btn variant="text" color="primary" size="small" @click="router.visit('/tasks')">
                            Все поручения →
                        </v-btn>
                    </div>
                    <div class="px-2 pb-3">
                        <div
                            v-for="task in priorityTasks"
                            :key="task.id"
                            class="skydesk-task d-flex align-center ga-3 px-3 py-3"
                            style="border-radius:11px;cursor:pointer"
                            @click="openTask(task.id)"
                        >
                            <div class="flex-grow-1">
                                <div class="text-body-2 font-weight-bold">{{ task.title }}</div>
                                <div class="text-caption text-medium-emphasis">
                                    {{ store.getStatus(task.status_id)?.label }}
                                </div>
                            </div>
                            <v-chip
                                size="x-small"
                                variant="tonal"
                                :style="{ color: store.getPriority(task.priority_id)?.color }"
                            >
                                {{ store.getPriority(task.priority_id)?.label }}
                            </v-chip>
                        </div>
                    </div>
                </v-card>
            </v-col>

            <v-col cols="12" md="4">
                <v-card class="mb-4">
                    <div class="d-flex align-center justify-space-between px-5 pt-5 pb-2">
                        <h2 class="text-subtitle-1 font-weight-bold mb-0">Сегодня</h2>
                        <v-btn variant="text" color="primary" size="small" @click="router.visit('/calendar')">
                            Календарь →
                        </v-btn>
                    </div>
                    <div class="px-5 pb-4">
                        <div
                            v-for="item in agenda"
                            :key="item.id"
                            class="d-flex ga-3 py-3 skydesk-row-divider"
                            style="cursor:pointer"
                            @click="openEvent(item.id)"
                        >
                            <div class="text-caption text-medium-emphasis" style="width:43px">{{ item.time }}</div>
                            <div>
                                <div class="text-body-2 font-weight-bold d-flex align-center ga-2">
                                    <span
                                        style="width:7px;height:7px;border-radius:50%;display:inline-block"
                                        :style="{ background: item.dot }"
                                    />
                                    {{ item.title }}
                                </div>
                                <div class="text-caption text-medium-emphasis mt-1">{{ item.desc }}</div>
                            </div>
                        </div>
                        <div v-if="!agenda.length" class="text-caption text-medium-emphasis py-3">
                            Нет событий на сегодня
                        </div>
                    </div>
                </v-card>

                <v-card v-if="financePreview">
                    <div class="d-flex align-center justify-space-between px-5 pt-5 pb-2">
                        <h2 class="text-subtitle-1 font-weight-bold mb-0">Финансы</h2>
                        <v-btn variant="text" color="primary" size="small" @click="router.visit('/finance')">
                            Открыть →
                        </v-btn>
                    </div>
                    <div
                        class="mx-5 mb-5 pa-4 skydesk-accent-panel"
                        style="cursor:pointer"
                        @click="openAdvance(financePreview.id)"
                    >
                        <div class="d-flex justify-space-between text-caption font-weight-bold text-primary">
                            <span>{{ financePreview.label }}</span>
                            <span>{{ financePreview.count }}</span>
                        </div>
                        <div class="text-h5 font-weight-bold my-2">{{ financePreview.amount }}</div>
                        <div class="text-caption text-medium-emphasis">{{ financePreview.hint }}</div>
                    </div>
                </v-card>
            </v-col>
        </v-row>
    </AppLayout>
</template>
