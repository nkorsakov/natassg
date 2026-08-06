<script setup>
import { computed } from 'vue';
import { dictChipStyle } from '@/utils/dictColor';

const props = defineProps({
    payload: { type: Object, required: true },
    periodFrom: { type: String, default: '' },
    periodTo: { type: String, default: '' },
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

const movementTone = (tx) => {
    if (tx.type === 'income' || Number(tx.amount) > 0) return 'in';
    if (tx.type === 'expense' || Number(tx.amount) < 0) return 'out';
    return 'xfer';
};

const moneyTiles = computed(() => [
    {
        key: 'open',
        label: 'Входящий остаток',
        value: formatMoney(finance.value.opening_on_hand),
        icon: 'mdi-tray-arrow-down',
        bg: 'rgba(105, 87, 238, 0.12)',
        color: '#6957EE',
    },
    {
        key: 'in',
        label: 'Приходы',
        value: `+${formatMoney(finance.value.income_total)}`,
        icon: 'mdi-arrow-down-bold-circle-outline',
        bg: 'rgba(55, 168, 120, 0.14)',
        color: '#37A878',
    },
    {
        key: 'out',
        label: 'Расходы',
        value: formatMoney(finance.value.expense_total),
        icon: 'mdi-arrow-up-bold-circle-outline',
        bg: 'rgba(233, 102, 103, 0.14)',
        color: '#E96667',
    },
    {
        key: 'close',
        label: 'Исходящий остаток',
        value: formatMoney(finance.value.closing_on_hand),
        icon: 'mdi-tray-arrow-up',
        bg: 'rgba(91, 141, 239, 0.14)',
        color: '#5B8DEF',
    },
]);

const toggle = (id) => emit('toggle-task', id);
</script>

<template>
    <div class="report-preview">
        <header class="report-preview__hero mb-5">
            <div class="report-preview__eyebrow">Отчёт руководителю</div>
            <h1
                class="report-preview__title mb-2"
                :class="{ 'report-preview__title--compact': compact }"
            >
                Итоги периода
            </h1>
            <p class="report-preview__period mb-1">{{ formatPeriod }}</p>
            <p v-if="subject.name" class="report-preview__subject mb-0">
                <span class="report-preview__avatar">{{ subject.initials || '·' }}</span>
                {{ subject.name }}
                <span v-if="subject.role" class="text-medium-emphasis"> · {{ subject.role }}</span>
            </p>
        </header>

        <section class="mb-6">
            <div class="report-preview__section-head mb-3">
                <div class="report-preview__section-icon" style="background:rgba(105,87,238,.14);color:#6957EE">
                    <v-icon size="18">mdi-hand-coin-outline</v-icon>
                </div>
                <h2 class="report-preview__section-title">Деньги</h2>
            </div>
            <div class="report-preview__tiles">
                <div
                    v-for="tile in moneyTiles"
                    :key="tile.key"
                    class="report-preview__tile"
                    :style="{ '--tile-bg': tile.bg, '--tile-color': tile.color }"
                >
                    <div class="report-preview__tile-icon">
                        <v-icon size="18" :icon="tile.icon" />
                    </div>
                    <div class="report-preview__tile-body">
                        <div class="report-preview__tile-label">{{ tile.label }}</div>
                        <div class="report-preview__tile-value">
                            {{ tile.value }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mb-6">
            <div class="report-preview__section-head mb-3">
                <div class="report-preview__section-icon" style="background:rgba(55,168,120,.14);color:#37A878">
                    <v-icon size="18">mdi-check-circle-outline</v-icon>
                </div>
                <h2 class="report-preview__section-title">Задачи</h2>
            </div>

            <div class="report-preview__block report-preview__block--closed mb-3">
                <div class="report-preview__block-head">
                    <span class="report-preview__dot" style="background:#37A878" />
                    Закрыты
                    <span class="text-medium-emphasis">
                        ({{ closedVisible.length }}{{ editable && closedAll.length !== closedVisible.length ? ` из ${closedAll.length}` : '' }})
                    </span>
                </div>
                <div v-if="!closedAll.length" class="text-caption text-medium-emphasis px-4 pb-4">
                    Нет закрытых поручений за период.
                </div>
                <div
                    v-for="t in (editable ? closedAll : closedVisible)"
                    :key="`c-${t.id}`"
                    class="report-preview__row"
                    :class="{ 'report-preview__row--excluded': editable && isExcluded(t.id) }"
                >
                    <div
                        class="report-preview__row-accent"
                        :style="{ background: t.status_color || t.type_color || '#37A878' }"
                    />
                    <div class="min-w-0 flex-grow-1">
                        <div
                            class="text-body-2 font-weight-medium"
                            :class="{ 'report-preview__strike': editable && isExcluded(t.id) }"
                        >
                            {{ t.title }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            {{ t.type_label || 'Поручение' }}
                            <span v-if="t.closed_at"> · {{ t.closed_at }}</span>
                        </div>
                    </div>
                    <div class="d-flex align-center ga-1 flex-shrink-0">
                        <v-chip
                            size="x-small"
                            variant="flat"
                            class="skydesk-pill"
                            :style="dictChipStyle(t.status_color || '#626571')"
                        >
                            {{ t.status_label || 'Готово' }}
                        </v-chip>
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
            </div>

            <div class="report-preview__block report-preview__block--active mb-3">
                <div class="report-preview__block-head">
                    <span class="report-preview__dot" style="background:#6957EE" />
                    В работе за период
                    <span class="text-medium-emphasis">
                        ({{ activeVisible.length }}{{ editable && activeAll.length !== activeVisible.length ? ` из ${activeAll.length}` : '' }})
                    </span>
                </div>
                <div v-if="!activeAll.length" class="text-caption text-medium-emphasis px-4 pb-4">
                    Нет активных поручений за период.
                </div>
                <div
                    v-for="t in (editable ? activeAll : activeVisible)"
                    :key="`a-${t.id}`"
                    class="report-preview__row"
                    :class="{ 'report-preview__row--excluded': editable && isExcluded(t.id) }"
                >
                    <div
                        class="report-preview__row-accent"
                        :style="{ background: t.status_color || t.type_color || '#6957EE' }"
                    />
                    <div class="min-w-0 flex-grow-1">
                        <div
                            class="text-body-2 font-weight-medium"
                            :class="{ 'report-preview__strike': editable && isExcluded(t.id) }"
                        >
                            {{ t.title }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            {{ t.type_label || 'Поручение' }}
                        </div>
                    </div>
                    <div class="d-flex align-center ga-1 flex-shrink-0">
                        <v-chip
                            size="x-small"
                            variant="flat"
                            class="skydesk-pill"
                            :style="dictChipStyle(t.status_color || '#6957EE')"
                        >
                            {{ t.status_label || '—' }}
                        </v-chip>
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
            </div>

            <div class="report-preview__block report-preview__block--events">
                <div class="report-preview__block-head">
                    <span class="report-preview__dot" style="background:#FFAD4D" />
                    События
                    <span class="text-medium-emphasis">({{ work.events?.length || 0 }})</span>
                </div>
                <div v-if="!(work.events || []).length" class="text-caption text-medium-emphasis px-4 pb-4">
                    Нет событий за период.
                </div>
                <div
                    v-for="e in work.events || []"
                    :key="`e-${e.id}`"
                    class="report-preview__row"
                >
                    <div
                        class="report-preview__row-accent"
                        :style="{ background: e.type_color || '#FFAD4D' }"
                    />
                    <div class="min-w-0 flex-grow-1">
                        <div class="text-body-2 font-weight-medium">{{ e.title }}</div>
                        <div class="text-caption text-medium-emphasis">
                            {{ formatEventWhen(e) }}
                            <span v-if="e.place"> · {{ e.place }}</span>
                        </div>
                    </div>
                    <v-chip
                        size="x-small"
                        variant="flat"
                        class="skydesk-pill"
                        :style="dictChipStyle(e.type_color || '#FFAD4D')"
                    >
                        {{ e.type_label || 'Событие' }}
                    </v-chip>
                </div>
            </div>
        </section>

        <section>
            <div class="report-preview__section-head mb-3">
                <div class="report-preview__section-icon" style="background:rgba(233,102,103,.14);color:#E96667">
                    <v-icon size="18">mdi-swap-horizontal</v-icon>
                </div>
                <h2 class="report-preview__section-title">Движение денежных средств</h2>
            </div>

            <div class="report-preview__block">
                <div v-if="!(finance.movements || []).length" class="text-caption text-medium-emphasis pa-4">
                    За период движений не было.
                </div>
                <div
                    v-for="tx in finance.movements || []"
                    :key="tx.id"
                    class="report-preview__row report-preview__row--money"
                    :class="`report-preview__row--${movementTone(tx)}`"
                >
                    <div class="min-w-0 flex-grow-1">
                        <div class="text-body-2 font-weight-medium">{{ tx.title }}</div>
                        <div class="text-caption text-medium-emphasis">
                            {{ tx.occurred_at }}
                            · {{ tx.type }}
                        </div>
                    </div>
                    <div class="report-preview__amount flex-shrink-0">
                        {{ movementSign(tx) }}{{ formatMoney(Math.abs(Number(tx.amount || 0))) }}
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped>
.report-preview__hero {
    position: relative;
    padding: 20px 20px 18px;
    border-radius: 20px;
    overflow: hidden;
    background:
        radial-gradient(120% 140% at 0% 0%, rgba(105, 87, 238, 0.22), transparent 55%),
        radial-gradient(100% 120% at 100% 0%, rgba(255, 173, 77, 0.18), transparent 50%),
        radial-gradient(90% 100% at 80% 100%, rgba(55, 168, 120, 0.12), transparent 45%),
        rgba(var(--v-theme-surface), 0.72);
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.report-preview__eyebrow {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: rgb(var(--v-theme-primary));
    margin-bottom: 6px;
}

.report-preview__title {
    font-family: Fraunces, Georgia, serif;
    font-size: 1.85rem;
    letter-spacing: -0.03em;
    font-weight: 700;
    line-height: 1.15;
}

.report-preview__title--compact {
    font-size: 1.4rem;
}

.report-preview__period {
    font-size: 0.95rem;
    font-weight: 600;
    color: rgba(var(--v-theme-on-surface), 0.72);
}

.report-preview__subject {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.8rem;
    margin-top: 10px;
}

.report-preview__avatar {
    width: 24px;
    height: 24px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    font-size: 10px;
    font-weight: 700;
    color: #fff;
    background: rgb(var(--v-theme-primary));
}

.report-preview__section-head {
    display: flex;
    align-items: center;
    gap: 10px;
}

.report-preview__section-icon {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: grid;
    place-items: center;
}

.report-preview__section-title {
    font-size: 1rem;
    font-weight: 700;
    margin: 0;
}

.report-preview__tiles {
    display: flex;
    flex-direction: column;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    background: rgb(var(--v-theme-surface));
}

.report-preview__tile {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    min-height: 64px;
    background: color-mix(in srgb, var(--tile-bg) 70%, transparent);
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.report-preview__tile:first-child {
    border-top: 0;
}

.report-preview__tile-icon {
    flex-shrink: 0;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    color: var(--tile-color);
    background: color-mix(in srgb, var(--tile-color) 16%, transparent);
}

.report-preview__tile-body {
    min-width: 0;
    flex: 1;
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
}

.report-preview__tile-label {
    font-size: 0.8rem;
    color: rgba(var(--v-theme-on-surface), 0.65);
}

.report-preview__tile-value {
    font-family: Fraunces, Georgia, serif;
    font-weight: 700;
    font-size: 1.1rem;
    letter-spacing: -0.02em;
    line-height: 1.2;
    color: var(--tile-color);
    text-align: right;
    font-variant-numeric: tabular-nums;
}

@media (min-width: 700px) {
    .report-preview__tiles {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        border: 0;
        background: transparent;
        overflow: visible;
    }

    .report-preview__tile {
        flex-direction: column;
        align-items: flex-start;
        gap: 0;
        border-radius: 16px;
        border: 0;
        padding: 14px 14px 12px;
        min-height: 106px;
        background: var(--tile-bg);
    }

    .report-preview__tile-icon {
        width: auto;
        height: auto;
        border-radius: 0;
        background: transparent;
        margin-bottom: 8px;
        display: block;
        place-items: unset;
    }

    .report-preview__tile-body {
        flex-direction: column;
        align-items: flex-start;
        justify-content: flex-start;
        gap: 2px;
        width: 100%;
    }

    .report-preview__tile-value {
        font-size: 1.15rem;
        text-align: left;
    }
}

.report-preview__block {
    border-radius: 16px;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    background: rgb(var(--v-theme-surface));
    overflow: hidden;
}

.report-preview__block--closed {
    background:
        linear-gradient(180deg, rgba(55, 168, 120, 0.08), transparent 48px),
        rgb(var(--v-theme-surface));
}

.report-preview__block--active {
    background:
        linear-gradient(180deg, rgba(105, 87, 238, 0.08), transparent 48px),
        rgb(var(--v-theme-surface));
}

.report-preview__block--events {
    background:
        linear-gradient(180deg, rgba(255, 173, 77, 0.1), transparent 48px),
        rgb(var(--v-theme-surface));
}

.report-preview__block-head {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 14px 16px 10px;
    font-size: 0.8rem;
    font-weight: 700;
}

.report-preview__dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

.report-preview__row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 16px;
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    position: relative;
}

.report-preview__row-accent {
    position: absolute;
    left: 0;
    top: 10px;
    bottom: 10px;
    width: 3px;
    border-radius: 0 3px 3px 0;
}

.report-preview__row--excluded {
    opacity: 0.45;
}

.report-preview__strike {
    text-decoration: line-through;
}

.report-preview__row--money {
    padding-left: 16px;
}

.report-preview__row--in {
    background: rgba(55, 168, 120, 0.05);
}

.report-preview__row--out {
    background: rgba(233, 102, 103, 0.05);
}

.report-preview__amount {
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.report-preview__row--in .report-preview__amount {
    color: #37A878;
}

.report-preview__row--out .report-preview__amount {
    color: #E96667;
}

.report-preview__row--xfer .report-preview__amount {
    color: #5B8DEF;
}
</style>
