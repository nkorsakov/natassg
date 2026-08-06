<script setup>
import { computed } from 'vue';

const props = defineProps({
    payload: { type: Object, required: true },
    periodFrom: { type: String, default: '' },
    periodTo: { type: String, default: '' },
    /** @type {{ type: import('vue').PropType<Record<number, boolean>> }} */
    excludedTaskIds: { type: Object, default: () => ({}) },
    editable: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle-task']);

const subject = computed(() => props.payload?.subject || {});
const work = computed(() => props.payload?.work || { closed: [], active: [], events: [] });
const finance = computed(() => props.payload?.finance || { movements: [] });

const isExcluded = (id) => !!props.excludedTaskIds?.[id];

const closedVisible = computed(() =>
    (work.value.closed || []).filter((t) => !isExcluded(t.id)),
);
const activeVisible = computed(() =>
    (work.value.active || []).filter((t) => !isExcluded(t.id)),
);
const closedAll = computed(() => work.value.closed || []);
const activeAll = computed(() => work.value.active || []);

const formatMoney = (n) => `${Number(n || 0).toLocaleString('ru-RU')} ₽`;

const formatPeriod = computed(() => {
    const from = props.periodFrom || props.payload?.period?.from;
    const to = props.periodTo || props.payload?.period?.to;
    const opts = { day: 'numeric', month: 'long', year: 'numeric' };
    const a = from ? new Date(`${from}T12:00:00`).toLocaleDateString('ru-RU', opts) : '—';
    const b = to ? new Date(`${to}T12:00:00`).toLocaleDateString('ru-RU', opts) : '—';
    return `${a} — ${b}`;
});

const formatEventWhen = (e) => {
    if (!e?.start) return '';
    if (e.all_day) {
        return new Date(`${String(e.start).slice(0, 10)}T12:00:00`).toLocaleDateString('ru-RU', {
            day: 'numeric',
            month: 'short',
        });
    }
    const d = new Date(e.start);
    return d.toLocaleString('ru-RU', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const movementSign = (tx) => {
    const amount = Number(tx.amount || 0);
    if (tx.type === 'income' || amount > 0) return '+';
    if (amount < 0) return '';
    if (tx.type === 'expense') return '−';
    return '';
};

const movementColor = (tx) => {
    if (tx.type === 'income' || Number(tx.amount) > 0) return 'success';
    if (tx.type === 'expense' || Number(tx.amount) < 0) return 'error';
    return undefined;
};

const toggle = (id) => emit('toggle-task', id);
</script>

<template>
    <div>
        <div class="mb-6">
            <h1
                class="mb-1"
                :style="{
                    fontFamily: 'Fraunces,Georgia,serif',
                    fontSize: compact ? '1.35rem' : '1.75rem',
                    letterSpacing: '-.03em',
                    fontWeight: 700,
                }"
            >
                Итоги периода
            </h1>
            <p class="text-body-2 text-medium-emphasis mb-1">{{ formatPeriod }}</p>
            <p v-if="subject.name" class="text-caption text-medium-emphasis mb-0">
                {{ subject.name }}
                <span v-if="subject.role"> · {{ subject.role }}</span>
            </p>
        </div>

        <v-card class="pa-5 mb-5 skydesk-accent-panel">
            <div class="text-caption font-weight-bold text-primary mb-3">На руках</div>
            <div class="d-flex flex-wrap ga-6">
                <div>
                    <div class="text-caption text-medium-emphasis">Входящий</div>
                    <div class="text-h6 font-weight-bold" style="font-family:Fraunces,Georgia,serif">
                        {{ formatMoney(finance.opening_on_hand) }}
                    </div>
                </div>
                <div>
                    <div class="text-caption text-medium-emphasis">Исходящий</div>
                    <div class="text-h6 font-weight-bold" style="font-family:Fraunces,Georgia,serif">
                        {{ formatMoney(finance.closing_on_hand) }}
                    </div>
                </div>
                <div>
                    <div class="text-caption text-medium-emphasis">Приходы</div>
                    <div class="text-body-1 font-weight-bold text-success">
                        +{{ formatMoney(finance.income_total) }}
                    </div>
                </div>
                <div>
                    <div class="text-caption text-medium-emphasis">Расходы</div>
                    <div class="text-body-1 font-weight-bold text-error">
                        {{ formatMoney(finance.expense_total) }}
                    </div>
                </div>
            </div>
        </v-card>

        <section class="mb-6">
            <h2 class="text-subtitle-1 font-weight-bold mb-3">Работа</h2>

            <v-card class="pa-4 mb-3">
                <div class="text-caption font-weight-bold mb-2">
                    Закрыты
                    <span class="text-medium-emphasis">
                        ({{ closedVisible.length }}{{ editable && closedAll.length !== closedVisible.length ? ` из ${closedAll.length}` : '' }})
                    </span>
                </div>
                <div v-if="!closedAll.length" class="text-caption text-medium-emphasis">
                    Нет закрытых поручений за период.
                </div>
                <div
                    v-for="t in (editable ? closedAll : closedVisible)"
                    :key="`c-${t.id}`"
                    class="d-flex align-start justify-space-between ga-3 py-2"
                    :style="{
                        borderBottom: '1px solid rgba(var(--v-border-color),var(--v-border-opacity))',
                        opacity: editable && isExcluded(t.id) ? 0.45 : 1,
                    }"
                >
                    <div class="min-w-0">
                        <div
                            class="text-body-2 font-weight-medium"
                            :style="editable && isExcluded(t.id) ? { textDecoration: 'line-through' } : {}"
                        >
                            {{ t.title }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            {{ t.type_label || 'Поручение' }}
                            <span v-if="t.closed_at"> · {{ t.closed_at }}</span>
                        </div>
                    </div>
                    <div class="d-flex align-center ga-1 flex-shrink-0">
                        <v-chip size="x-small" variant="tonal">{{ t.status_label || 'Готово' }}</v-chip>
                        <v-btn
                            v-if="editable"
                            icon
                            variant="text"
                            size="small"
                            :title="isExcluded(t.id) ? 'Вернуть в отчёт' : 'Убрать из отчёта'"
                            @click="toggle(t.id)"
                        >
                            <v-icon size="18">
                                {{ isExcluded(t.id) ? 'mdi-eye-off-outline' : 'mdi-eye-outline' }}
                            </v-icon>
                        </v-btn>
                    </div>
                </div>
            </v-card>

            <v-card class="pa-4 mb-3">
                <div class="text-caption font-weight-bold mb-2">
                    В работе за период
                    <span class="text-medium-emphasis">
                        ({{ activeVisible.length }}{{ editable && activeAll.length !== activeVisible.length ? ` из ${activeAll.length}` : '' }})
                    </span>
                </div>
                <div v-if="!activeAll.length" class="text-caption text-medium-emphasis">
                    Нет активных поручений за период.
                </div>
                <div
                    v-for="t in (editable ? activeAll : activeVisible)"
                    :key="`a-${t.id}`"
                    class="d-flex align-start justify-space-between ga-3 py-2"
                    :style="{
                        borderBottom: '1px solid rgba(var(--v-border-color),var(--v-border-opacity))',
                        opacity: editable && isExcluded(t.id) ? 0.45 : 1,
                    }"
                >
                    <div class="min-w-0">
                        <div
                            class="text-body-2 font-weight-medium"
                            :style="editable && isExcluded(t.id) ? { textDecoration: 'line-through' } : {}"
                        >
                            {{ t.title }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            {{ t.type_label || 'Поручение' }}
                        </div>
                    </div>
                    <div class="d-flex align-center ga-1 flex-shrink-0">
                        <v-chip size="x-small" variant="tonal">{{ t.status_label || '—' }}</v-chip>
                        <v-btn
                            v-if="editable"
                            icon
                            variant="text"
                            size="small"
                            :title="isExcluded(t.id) ? 'Вернуть в отчёт' : 'Убрать из отчёта'"
                            @click="toggle(t.id)"
                        >
                            <v-icon size="18">
                                {{ isExcluded(t.id) ? 'mdi-eye-off-outline' : 'mdi-eye-outline' }}
                            </v-icon>
                        </v-btn>
                    </div>
                </div>
            </v-card>

            <v-card class="pa-4">
                <div class="text-caption font-weight-bold mb-2">
                    События
                    <span class="text-medium-emphasis">({{ work.events?.length || 0 }})</span>
                </div>
                <div v-if="!(work.events || []).length" class="text-caption text-medium-emphasis">
                    Нет событий за период.
                </div>
                <div
                    v-for="e in work.events || []"
                    :key="`e-${e.id}`"
                    class="d-flex align-start justify-space-between ga-3 py-2"
                    style="border-bottom:1px solid rgba(var(--v-border-color),var(--v-border-opacity))"
                >
                    <div class="min-w-0">
                        <div class="text-body-2 font-weight-medium">{{ e.title }}</div>
                        <div class="text-caption text-medium-emphasis">
                            {{ formatEventWhen(e) }}
                            <span v-if="e.place"> · {{ e.place }}</span>
                        </div>
                    </div>
                    <v-chip size="x-small" variant="tonal">{{ e.type_label || 'Событие' }}</v-chip>
                </div>
            </v-card>
        </section>

        <section class="mb-2">
            <h2 class="text-subtitle-1 font-weight-bold mb-3">Движение средств</h2>
            <v-card class="pa-4">
                <div v-if="!(finance.movements || []).length" class="text-caption text-medium-emphasis">
                    За период движений не было.
                </div>
                <div
                    v-for="tx in finance.movements || []"
                    :key="tx.id"
                    class="d-flex align-start justify-space-between ga-3 py-2"
                    style="border-bottom:1px solid rgba(var(--v-border-color),var(--v-border-opacity))"
                >
                    <div class="min-w-0">
                        <div class="text-body-2 font-weight-medium">{{ tx.title }}</div>
                        <div class="text-caption text-medium-emphasis">
                            {{ tx.occurred_at }}
                            · {{ tx.type }}
                        </div>
                    </div>
                    <div
                        class="text-body-2 font-weight-bold flex-shrink-0"
                        :class="movementColor(tx) ? `text-${movementColor(tx)}` : ''"
                    >
                        {{ movementSign(tx) }}{{ formatMoney(Math.abs(Number(tx.amount || 0))) }}
                    </div>
                </div>
            </v-card>
        </section>
    </div>
</template>
