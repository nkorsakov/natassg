<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ReportPreview from '@/Components/ReportPreview.vue';

const props = defineProps({
    period_from: { type: String, required: true },
    period_to: { type: String, required: true },
    preview: { type: Object, required: true },
    recent: { type: Array, default: () => [] },
    created: { type: Object, default: null },
    creating: { type: Boolean, default: false },
});

const page = usePage();

const mode = ref(props.creating ? 'create' : 'list');
const excluded = ref({});
const copied = ref(false);
const copiedRecentId = ref(null);
const saving = ref(false);

watch(
    () => props.creating,
    (v) => {
        if (v) mode.value = 'create';
    },
);

watch(
    () => [props.period_from, props.period_to],
    () => {
        excluded.value = {};
    },
);

const createdFlash = computed(() => props.created || page.props.flash?.created_report || null);

const formatMoney = (n) => `${Number(n || 0).toLocaleString('ru-RU')} ₽`;

const formatPeriod = (from, to) => {
    const opts = { day: 'numeric', month: 'short', year: 'numeric' };
    const a = from ? new Date(`${from}T12:00:00`).toLocaleDateString('ru-RU', opts) : '—';
    const b = to ? new Date(`${to}T12:00:00`).toLocaleDateString('ru-RU', opts) : '—';
    return `${a} — ${b}`;
};

const formatCreatedAt = (iso) => {
    if (!iso) return '';
    return new Date(iso).toLocaleString('ru-RU', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const localDateKey = (d) => {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
};

const toLocalDate = (value) => {
    if (!value) return null;
    if (value instanceof Date && !Number.isNaN(value.getTime())) return value;
    const s = String(value);
    if (/^\d{4}-\d{2}-\d{2}/.test(s)) {
        const [y, m, d] = s.slice(0, 10).split('-').map(Number);
        return new Date(y, m - 1, d);
    }
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const fromLocalDate = (value) => {
    const js = toLocalDate(value);
    if (!js) return '';
    return localDateKey(js);
};

const startOfWeek = (date) => {
    const d = new Date(date);
    d.setHours(12, 0, 0, 0);
    const day = d.getDay();
    const diff = day === 0 ? -6 : 1 - day;
    d.setDate(d.getDate() + diff);
    return d;
};

const endOfWeek = (date) => {
    const d = startOfWeek(date);
    d.setDate(d.getDate() + 6);
    return d;
};

const rangeFromProps = () => {
    const a = toLocalDate(props.period_from);
    const b = toLocalDate(props.period_to);
    if (a && b) return [a, b];
    if (a) return [a];
    return [];
};

const dateRange = ref(rangeFromProps());
let skipRangeWatch = false;

watch(
    () => [props.period_from, props.period_to],
    () => {
        skipRangeWatch = true;
        dateRange.value = rangeFromProps();
        queueMicrotask(() => {
            skipRangeWatch = false;
        });
    },
);

const reloadPeriod = (from, to, { creating = true } = {}) => {
    router.get(
        '/reports',
        {
            period_from: from,
            period_to: to,
            ...(creating ? { creating: 1 } : {}),
        },
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

watch(
    dateRange,
    (value) => {
        if (skipRangeWatch) return;
        const arr = (Array.isArray(value) ? value : []).map(toLocalDate).filter(Boolean);
        if (arr.length < 2) return;

        let from = fromLocalDate(arr[0]);
        let to = fromLocalDate(arr[arr.length - 1]);
        if (!from || !to) return;
        if (from > to) [from, to] = [to, from];
        if (from === props.period_from && to === props.period_to) return;

        reloadPeriod(from, to);
    },
    { deep: true },
);

const applyThisWeek = () => {
    const now = new Date();
    reloadPeriod(localDateKey(startOfWeek(now)), localDateKey(endOfWeek(now)));
};

const applyLastWeek = () => {
    const now = new Date();
    const last = new Date(now);
    last.setDate(last.getDate() - 7);
    reloadPeriod(localDateKey(startOfWeek(last)), localDateKey(endOfWeek(last)));
};

const startCreate = () => {
    mode.value = 'create';
    excluded.value = {};
    reloadPeriod(props.period_from, props.period_to, { creating: true });
};

const backToList = () => {
    mode.value = 'list';
    router.get('/reports', {}, { preserveScroll: true, preserveState: true, replace: true });
};

const toggleTask = (id) => {
    const next = { ...excluded.value };
    if (next[id]) delete next[id];
    else next[id] = true;
    excluded.value = next;
};

const excludedIds = computed(() =>
    Object.keys(excluded.value)
        .filter((id) => excluded.value[id])
        .map((id) => Number(id)),
);

const excludedCount = computed(() => excludedIds.value.length);

const form = useForm({
    period_from: props.period_from,
    period_to: props.period_to,
    exclude_task_ids: [],
});

watch(
    () => [props.period_from, props.period_to],
    ([from, to]) => {
        form.period_from = from;
        form.period_to = to;
    },
);

const generate = () => {
    form.period_from = props.period_from;
    form.period_to = props.period_to;
    form.exclude_task_ids = excludedIds.value;
    saving.value = true;
    form.post('/reports', {
        preserveScroll: true,
        onSuccess: () => {
            mode.value = 'list';
        },
        onFinish: () => {
            saving.value = false;
        },
    });
};

const copyUrl = async (url, recentId = null) => {
    try {
        await navigator.clipboard.writeText(url);
        if (recentId != null) {
            copiedRecentId.value = recentId;
            copied.value = false;
            setTimeout(() => {
                if (copiedRecentId.value === recentId) copiedRecentId.value = null;
            }, 1600);
        } else {
            copied.value = true;
            copiedRecentId.value = null;
            setTimeout(() => {
                copied.value = false;
            }, 1600);
        }
    } catch {
        window.prompt('Скопируйте ссылку:', url);
    }
};

const removeReport = (id) => {
    router.delete(`/reports/${id}`, { preserveScroll: true });
};

const pageTitle = computed(() => (mode.value === 'create' ? 'Новый отчёт' : 'Отчёты'));
const pageSubtitle = computed(() =>
    mode.value === 'create'
        ? 'Период, превью и формирование ссылки для руководителя.'
        : 'Сформированные ссылки для руководителя.',
);
</script>

<template>
    <AppLayout :title="pageTitle" :subtitle="pageSubtitle" :show-fab="false">
        <Head :title="pageTitle" />

        <template #actions>
            <v-btn
                v-if="mode === 'list'"
                color="primary"
                prepend-icon="mdi-plus"
                @click="startCreate"
            >
                Создать отчёт
            </v-btn>
            <v-btn
                v-else
                variant="tonal"
                prepend-icon="mdi-arrow-left"
                @click="backToList"
            >
                К списку
            </v-btn>
        </template>

        <!-- LIST -->
        <div v-if="mode === 'list'">
            <v-alert
                v-if="createdFlash"
                type="success"
                variant="tonal"
                class="mb-4"
                border="start"
            >
                <div class="text-subtitle-2 font-weight-bold mb-1">Ссылка готова</div>
                <div class="text-body-2 mb-2">
                    {{ formatPeriod(createdFlash.report?.period_from, createdFlash.report?.period_to) }}
                    · закрыто {{ createdFlash.report?.summary?.closed_count ?? 0 }},
                    в работе {{ createdFlash.report?.summary?.active_count ?? 0 }},
                    событий {{ createdFlash.report?.summary?.events_count ?? 0 }}
                </div>
                <div class="d-flex ga-2 flex-wrap">
                    <v-text-field
                        :model-value="createdFlash.url"
                        readonly
                        hide-details
                        density="compact"
                        variant="outlined"
                        class="flex-grow-1"
                        style="min-width:180px;max-width:480px"
                    />
                    <v-btn
                        color="primary"
                        :prepend-icon="copied ? 'mdi-check' : 'mdi-content-copy'"
                        @click="copyUrl(createdFlash.url)"
                    >
                        {{ copied ? 'Скопировано' : 'Копировать' }}
                    </v-btn>
                    <v-btn
                        variant="tonal"
                        prepend-icon="mdi-open-in-new"
                        :href="createdFlash.url"
                        target="_blank"
                        rel="noopener"
                    >
                        Открыть
                    </v-btn>
                </div>
            </v-alert>

            <v-card class="pa-4" style="max-width:860px">
                <div v-if="!recent.length" class="py-8 text-center">
                    <div class="text-body-1 font-weight-medium mb-1">Пока нет отчётов</div>
                    <div class="text-caption text-medium-emphasis mb-4">
                        Сформируйте первый снимок и отправьте ссылку руководителю.
                    </div>
                    <v-btn color="primary" prepend-icon="mdi-plus" @click="startCreate">
                        Создать отчёт
                    </v-btn>
                </div>

                <div
                    v-for="r in recent"
                    :key="r.id"
                    class="d-flex align-center ga-2 py-3"
                    style="border-bottom:1px solid rgba(var(--v-border-color),var(--v-border-opacity))"
                >
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-body-2 font-weight-medium">
                            {{ formatPeriod(r.period_from, r.period_to) }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            {{ formatMoney(r.summary?.opening_on_hand) }}
                            → {{ formatMoney(r.summary?.closing_on_hand) }}
                            <span v-if="r.created_at"> · {{ formatCreatedAt(r.created_at) }}</span>
                        </div>
                    </div>
                    <v-btn
                        icon
                        variant="text"
                        size="small"
                        :title="copiedRecentId === r.id ? 'Скопировано' : 'Копировать'"
                        @click="copyUrl(r.url, r.id)"
                    >
                        <v-icon size="18">
                            {{ copiedRecentId === r.id ? 'mdi-check' : 'mdi-content-copy' }}
                        </v-icon>
                    </v-btn>
                    <v-btn
                        icon
                        variant="text"
                        size="small"
                        :href="r.url"
                        target="_blank"
                        rel="noopener"
                        title="Открыть"
                    >
                        <v-icon size="18">mdi-open-in-new</v-icon>
                    </v-btn>
                    <v-btn
                        icon
                        variant="text"
                        size="small"
                        color="error"
                        title="Отозвать"
                        @click="removeReport(r.id)"
                    >
                        <v-icon size="18">mdi-delete-outline</v-icon>
                    </v-btn>
                </div>
            </v-card>
        </div>

        <!-- CREATE -->
        <div v-else style="max-width:860px">
            <v-card class="pa-4 mb-4">
                <div class="d-flex flex-wrap ga-2 mb-3">
                    <v-btn size="small" variant="tonal" @click="applyThisWeek">Эта неделя</v-btn>
                    <v-btn size="small" variant="tonal" @click="applyLastWeek">Прошлая неделя</v-btn>
                </div>

                <v-date-input
                    v-model="dateRange"
                    multiple="range"
                    label="Период"
                    prepend-icon=""
                    prepend-inner-icon="mdi-calendar-range"
                    placeholder="дд.мм.гггг — дд.мм.гггг"
                    input-format="dd.mm.yyyy"
                    :first-day-of-week="1"
                    hide-actions
                    hide-details="auto"
                    class="skydesk-date-range mb-4"
                />

                <p v-if="excludedCount" class="text-caption text-medium-emphasis mb-0 mt-3">
                    Исключено поручений: {{ excludedCount }}
                </p>
                <div v-if="form.errors.period_from || form.errors.period_to" class="text-error text-caption mt-2">
                    {{ form.errors.period_from || form.errors.period_to }}
                </div>
            </v-card>

            <div class="d-flex align-center justify-space-between mb-2">
                <h2 class="text-subtitle-1 font-weight-bold mb-0">Превью</h2>
                <span class="text-caption text-medium-emphasis">Как увидит руководитель</span>
            </div>

            <v-card class="pa-5 mb-4">
                <ReportPreview
                    :payload="preview"
                    :period-from="period_from"
                    :period-to="period_to"
                    :excluded-task-ids="excluded"
                    editable
                    compact
                    @toggle-task="toggleTask"
                />
            </v-card>

            <v-card class="pa-4">
                <div class="text-caption text-medium-emphasis mb-3">
                    {{ formatPeriod(period_from, period_to) }}
                    <span v-if="excludedCount"> · −{{ excludedCount }} поруч.</span>
                </div>
                <v-btn
                    color="primary"
                    block
                    size="large"
                    prepend-icon="mdi-file-chart-outline"
                    :loading="saving || form.processing"
                    :disabled="form.processing"
                    @click="generate"
                >
                    Сформировать
                </v-btn>
            </v-card>
        </div>
    </AppLayout>
</template>

<style scoped>
.skydesk-date-range :deep(input) {
    cursor: pointer;
}
</style>
