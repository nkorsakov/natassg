<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useDisplay } from 'vuetify';
import AppLayout from '@/Layouts/AppLayout.vue';
import OwnerBadge from '@/Components/OwnerBadge.vue';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';
import { useWorkspaceUi } from '@/composables/useWorkspaceUi';
import { useIsAdmin } from '@/composables/useIsAdmin';
import { dictChipStyle } from '@/utils/dictColor';

const { mdAndUp } = useDisplay();
const store = useSkyDeskStore();
const { openTask, openEvent, openAdvance } = useWorkspaceUi();
const { isAdmin } = useIsAdmin();

const localDateKey = (offsetDays = 0) => {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
};

const today = localDateKey(0);
const tomorrow = localDateKey(1);

const isActiveTask = (t) => t && !['done', 'cancelled'].includes(t.status_id);

const startsOn = (value, day) => String(value || '').startsWith(day);

const stats = computed(() => [
    {
        value: String(store.activeTaskCount.value),
        label: 'активных поручений',
        icon: 'mdi-check-circle-outline',
        bg: '#eceaff',
        color: 'primary',
        href: '/tasks',
    },
    {
        value: String(store.waitingMoneyCount.value),
        label: 'ожидают финансирования',
        icon: 'mdi-currency-rub',
        bg: '#fff1dd',
        color: 'warning',
        href: '/finance',
    },
    {
        value: String(
            store.events.value.filter((e) => startsOn(e.start, today) || startsOn(e.start, tomorrow)).length,
        ),
        label: 'события сегодня / завтра',
        icon: 'mdi-calendar-month-outline',
        bg: '#e2f7ee',
        color: 'success',
        href: '/calendar?view=list',
    },
    {
        value: String(
            store.advances.value.filter((a) => ['received', 'reporting'].includes(a.status_id)).length,
        ),
        label: 'авансов на отчёте',
        icon: 'mdi-receipt-text-outline',
        bg: '#ffe9e9',
        color: 'error',
        href: '/finance',
    },
]);

const mapAgendaItem = (e) => ({
    id: e.id,
    time: e.allDay ? 'день' : String(e.start).slice(11, 16),
    title: e.title,
    user: e.user,
    desc: `${e.place || 'Без места'} · ${store.tasksForEvent(e.id).length} поруч.`,
    dot: store.getEventType(e.type_id)?.color || '#6957EE',
});

const agendaToday = computed(() =>
    store.events.value.filter((e) => startsOn(e.start, today)).map(mapAgendaItem),
);

const agendaTomorrow = computed(() =>
    store.events.value.filter((e) => startsOn(e.start, tomorrow)).map(mapAgendaItem),
);

const attentionTasks = computed(() => {
    const byId = new Map();

    const add = (task, reason) => {
        if (!isActiveTask(task)) return;
        const existing = byId.get(task.id);
        if (existing) {
            if (!existing.reasons.includes(reason)) existing.reasons.push(reason);
            return;
        }
        byId.set(task.id, { ...task, reasons: [reason] });
    };

    store.rootTasks.value
        .filter((t) => ['urgent', 'high'].includes(t.priority_id))
        .forEach((t) => add(t, store.getPriority(t.priority_id)?.label || 'Приоритет'));

    store.tasks.value.forEach((t) => {
        if (startsOn(t.deadline, today)) add(t, 'Дедлайн сегодня');
        if (startsOn(t.deadline, tomorrow)) add(t, 'Дедлайн завтра');
    });

    store.events.value.forEach((e) => {
        const dayLabel = startsOn(e.start, today)
            ? 'Событие сегодня'
            : startsOn(e.start, tomorrow)
                ? 'Событие завтра'
                : null;
        if (!dayLabel) return;
        store.tasksForEvent(e.id).forEach((t) => add(t, dayLabel));
    });

    const rank = (task) => {
        if (task.priority_id === 'urgent') return 0;
        if (task.priority_id === 'high') return 1;
        if (task.reasons.some((r) => r.includes('сегодня'))) return 2;
        return 3;
    };

    return [...byId.values()]
        .sort((a, b) => rank(a) - rank(b) || String(a.title).localeCompare(String(b.title), 'ru'))
        .slice(0, 12);
});

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
        subtitle="Вот что требует вашего внимания сегодня и завтра."
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
                :class="{ 'skydesk-stat-click': !!stat.href }"
                @click="stat.href && router.visit(stat.href)"
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

        <v-card class="mb-5">
            <div class="d-flex align-center justify-space-between px-5 pt-5 pb-2">
                <h2 class="text-subtitle-1 font-weight-bold mb-0">Календарь</h2>
                <v-btn variant="text" color="primary" size="small" @click="router.visit('/calendar')">
                    Открыть →
                </v-btn>
            </div>
            <v-row dense class="px-3 pb-4">
                <v-col cols="12" md="6">
                    <div class="skydesk-agenda-day pa-3">
                        <div class="text-caption font-weight-bold text-medium-emphasis mb-2">Сегодня</div>
                        <div
                            v-for="item in agendaToday"
                            :key="item.id"
                            class="d-flex ga-3 py-3 skydesk-row-divider"
                            style="cursor:pointer"
                            @click="openEvent(item.id)"
                        >
                            <div class="text-caption text-medium-emphasis" style="width:43px;flex-shrink:0">{{ item.time }}</div>
                            <div class="min-w-0">
                                <div class="text-body-2 font-weight-bold d-flex align-center ga-2">
                                    <span
                                        style="width:7px;height:7px;border-radius:50%;display:inline-block;flex-shrink:0"
                                        :style="{ background: item.dot }"
                                    />
                                    <span class="text-truncate">{{ item.title }}</span>
                                    <OwnerBadge :show="isAdmin" :user="item.user" />
                                </div>
                                <div class="text-caption text-medium-emphasis mt-1">{{ item.desc }}</div>
                            </div>
                        </div>
                        <div v-if="!agendaToday.length" class="text-caption text-medium-emphasis py-3">
                            Нет событий на сегодня
                        </div>
                    </div>
                </v-col>
                <v-col cols="12" md="6">
                    <div class="skydesk-agenda-day pa-3">
                        <div class="text-caption font-weight-bold text-medium-emphasis mb-2">Завтра</div>
                        <div
                            v-for="item in agendaTomorrow"
                            :key="item.id"
                            class="d-flex ga-3 py-3 skydesk-row-divider"
                            style="cursor:pointer"
                            @click="openEvent(item.id)"
                        >
                            <div class="text-caption text-medium-emphasis" style="width:43px;flex-shrink:0">{{ item.time }}</div>
                            <div class="min-w-0">
                                <div class="text-body-2 font-weight-bold d-flex align-center ga-2">
                                    <span
                                        style="width:7px;height:7px;border-radius:50%;display:inline-block;flex-shrink:0"
                                        :style="{ background: item.dot }"
                                    />
                                    <span class="text-truncate">{{ item.title }}</span>
                                    <OwnerBadge :show="isAdmin" :user="item.user" />
                                </div>
                                <div class="text-caption text-medium-emphasis mt-1">{{ item.desc }}</div>
                            </div>
                        </div>
                        <div v-if="!agendaTomorrow.length" class="text-caption text-medium-emphasis py-3">
                            Нет событий на завтра
                        </div>
                    </div>
                </v-col>
            </v-row>
        </v-card>

        <v-row>
            <v-col cols="12" :md="financePreview ? 8 : 12">
                <v-card class="skydesk-task-list pa-2">
                    <div class="d-flex align-center justify-space-between px-3 pt-3 pb-2">
                        <h2 class="text-subtitle-1 font-weight-bold mb-0">Требуют внимания</h2>
                        <v-btn variant="text" color="primary" size="small" @click="router.visit('/tasks')">
                            Все поручения →
                        </v-btn>
                    </div>
                    <div class="d-flex flex-column ga-2 px-1 pb-2">
                        <div
                            v-for="task in attentionTasks"
                            :key="task.id"
                            class="skydesk-task d-flex align-center ga-3 px-3 py-3"
                            :style="{ borderLeftColor: store.getStatus(task.status_id)?.color || 'transparent' }"
                            @click="openTask(task.id)"
                        >
                            <div
                                class="skydesk-stat-icon flex-shrink-0"
                                :style="{ background: (store.getTaskType(task.type_id)?.color || '#6957EE') + '22' }"
                            >
                                <v-icon
                                    size="18"
                                    :icon="store.getTaskType(task.type_id)?.icon || 'mdi-checkbox-blank-circle-outline'"
                                    :style="{ color: store.getTaskType(task.type_id)?.color || '#6957EE' }"
                                />
                            </div>
                            <div class="flex-grow-1 min-w-0 overflow-hidden">
                                <div class="d-flex align-center ga-2 mb-1">
                                    <div class="text-body-2 font-weight-bold skydesk-task-title flex-grow-1">{{ task.title }}</div>
                                    <OwnerBadge :show="isAdmin" :user="task.user" />
                                </div>
                                <div class="text-caption text-medium-emphasis text-truncate">
                                    {{ task.reasons.join(' · ') }}
                                </div>
                            </div>
                            <v-chip
                                size="x-small"
                                variant="tonal"
                                class="skydesk-pill flex-shrink-0"
                                :style="dictChipStyle(store.getStatus(task.status_id)?.color)"
                            >
                                {{ store.getStatus(task.status_id)?.label }}
                            </v-chip>
                        </div>
                        <div v-if="!attentionTasks.length" class="pa-6 text-center text-medium-emphasis">
                            На сегодня и завтра всё спокойно
                        </div>
                    </div>
                </v-card>
            </v-col>

            <v-col v-if="financePreview" cols="12" md="4">
                <v-card>
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

<style scoped>
.skydesk-stat-click {
    cursor: pointer;
    transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
}

.skydesk-stat-click:hover {
    border-color: rgba(var(--v-theme-primary), 0.28);
    box-shadow: 0 1px 2px rgba(25, 24, 39, 0.04);
}

.skydesk-agenda-day {
    border-radius: 14px;
    background: rgba(var(--v-theme-on-surface), 0.03);
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    min-height: 100%;
}

.skydesk-task-list {
    background: rgba(var(--v-theme-on-surface), 0.03);
}

.skydesk-task {
    min-height: 56px;
    cursor: pointer;
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
</style>
