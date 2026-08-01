<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { useDisplay } from 'vuetify';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';
import { prepareUploadFile } from '@/utils/compressImage';
import { dictDotStyle } from '@/utils/dictColor';

const model = defineModel({ type: Boolean, default: false });
const props = defineProps({
    advanceId: { type: [String, Number], default: null },
});
const emit = defineEmits(['open-task']);

const { mdAndUp } = useDisplay();
const store = useSkyDeskStore();
const receiptInput = ref(null);
const receiptExpenseId = ref(null);
const uploadingReceipt = ref(false);
const previewReceipt = ref(null);
const titleInput = ref(null);
const skipWatch = ref(false);

const advance = computed(() => (props.advanceId ? store.getAdvance(props.advanceId) : null));
const expenseList = computed(() =>
    props.advanceId ? store.expensesForAdvance(props.advanceId) : [],
);
const statusItems = computed(() => store.dictionaries.value.advanceStatuses || []);
const methodItems = computed(() => store.dictionaries.value.disbursementMethods || []);
const articleItems = computed(() => store.dictionaries.value.expenseArticles || []);
const taskItems = computed(() =>
    store.tasks.value.map((t) => ({ id: t.id, title: t.title })),
);
const supplierItems = computed(() =>
    store.suppliers.value.map((s) => ({
        id: s.id,
        title: s.name || `Поставщик #${s.id}`,
    })),
);

const form = reactive({
    title: '',
    amount: '',
    status_id: 'pending',
    task_ids: [],
    disbursement_method_id: null,
    note: '',
});

const expenseDraft = reactive({
    amount: '',
    description: '',
    article_id: null,
    supplier_id: null,
});

const isDraftTitle = (title) => {
    const t = String(title || '').trim();
    return !t || t === 'Новая заявка на аванс' || t === 'Заявка на аванс';
};

const spent = computed(() => store.advanceSpent(props.advanceId));
const remaining = computed(() => store.advanceRemaining(props.advanceId));
const canSettle = computed(() =>
    ['issued', 'reporting'].includes(advance.value?.status_id) && remaining.value > 0,
);

watch(
    () => [model.value, props.advanceId, advance.value],
    async () => {
        if (!model.value || !advance.value) return;
        skipWatch.value = true;
        const draftTitle = isDraftTitle(advance.value.title);
        const draftAmount = !Number(advance.value.amount);
        form.title = draftTitle ? '' : advance.value.title;
        form.amount = draftAmount ? '' : advance.value.amount;
        form.status_id = advance.value.status_id;
        form.task_ids = [...(advance.value.task_ids || [])];
        form.disbursement_method_id = advance.value.disbursement_method_id || null;
        form.note = advance.value.note || '';
        expenseDraft.amount = '';
        expenseDraft.description = '';
        expenseDraft.article_id = articleItems.value[0]?.id || null;
        expenseDraft.supplier_id = null;
        await nextTick();
        skipWatch.value = false;
        if (draftTitle || draftAmount) {
            const el = titleInput.value?.$el?.querySelector?.('input');
            el?.focus?.();
        }
    },
    { immediate: true },
);

watch(
    form,
    () => {
        if (skipWatch.value || !model.value || !props.advanceId) return;

        if (form.status_id === 'issued') {
            if (!(Number(form.amount) > 0)) {
                window.alert('Перед выдачей укажите сумму больше нуля.');
                skipWatch.value = true;
                form.status_id = advance.value?.status_id || 'pending';
                nextTick(() => { skipWatch.value = false; });
                return;
            }
            if (!form.disbursement_method_id) {
                window.alert('Укажите способ выдачи (перевод / наличка).');
                skipWatch.value = true;
                form.status_id = advance.value?.status_id || 'pending';
                nextTick(() => { skipWatch.value = false; });
                return;
            }
        }

        if (
            advance.value
            && ['issued', 'reporting', 'closed'].includes(advance.value.status_id)
            && Number(form.amount) !== Number(advance.value.amount)
            && Number(form.amount) > 0
        ) {
            if (!window.confirm('Сумма уже выдавалась. Изменить с корректировкой кошелька?')) {
                skipWatch.value = true;
                form.amount = advance.value.amount;
                nextTick(() => { skipWatch.value = false; });
                return;
            }
        }

        if (advance.value?.status_id === 'closed' && form.status_id !== 'closed') {
            if (!window.confirm('Открыть закрытый аванс заново?')) {
                skipWatch.value = true;
                form.status_id = 'closed';
                nextTick(() => { skipWatch.value = false; });
                return;
            }
        }

        store.updateAdvance(props.advanceId, {
            title: form.title.trim(),
            amount: Number(form.amount) || 0,
            status_id: form.status_id,
            task_ids: form.task_ids,
            disbursement_method_id: form.disbursement_method_id,
            note: form.note,
        });
    },
    { deep: true },
);

const addExpense = () => {
    const amount = Number(expenseDraft.amount);
    if (!amount || !expenseDraft.article_id || !expenseDraft.supplier_id) {
        window.alert('Нужны сумма, статья и поставщик.');
        return;
    }
    store.addExpense(props.advanceId, {
        amount,
        description: expenseDraft.description,
        article_id: expenseDraft.article_id,
        supplier_id: expenseDraft.supplier_id,
    });
    expenseDraft.amount = '';
    expenseDraft.description = '';
};

const addReceipt = (expenseId) => {
    receiptExpenseId.value = expenseId;
    receiptInput.value?.click();
};

const onReceiptSelected = async (event) => {
    const files = [...(event.target.files || [])];
    event.target.value = '';
    if (!files.length || !receiptExpenseId.value) return;
    const expenseId = receiptExpenseId.value;
    uploadingReceipt.value = true;
    try {
        for (const raw of files) {
            const prepared = await prepareUploadFile(raw);
            store.addReceipt(expenseId, prepared.file);
        }
    } finally {
        uploadingReceipt.value = false;
        receiptExpenseId.value = null;
    }
};

const receiptsFor = (expenseId) => store.receiptsForExpense(expenseId) || [];

const isImageReceipt = (r) =>
    r.kind === 'image'
    || String(r.mime || '').startsWith('image/')
    || /\.(jpe?g|png|gif|webp|heic|bmp)$/i.test(String(r.name || r.original_name || ''));

const openReceipt = (r) => {
    if (isImageReceipt(r)) {
        previewReceipt.value = r;
        return;
    }
    window.open(r.url, '_blank', 'noopener,noreferrer');
};

const closeReceiptPreview = () => {
    previewReceipt.value = null;
};

const removeReceipt = (expenseId, receiptId) => {
    store.removeReceipt(expenseId, receiptId);
};

const openFirstTask = () => {
    const id = form.task_ids?.[0];
    if (id) emit('open-task', id);
};
</script>

<template>
    <v-dialog
        v-model="model"
        :fullscreen="!mdAndUp"
        :max-width="mdAndUp ? 720 : undefined"
        scrollable
    >
        <v-card v-if="advance" class="d-flex flex-column" :style="mdAndUp ? 'max-height:90vh' : 'min-height:100%'">
            <v-card-title class="d-flex align-center justify-space-between px-6 pt-5">
                <div>
                    <div class="text-caption text-medium-emphasis mb-1">Аванс</div>
                    <span class="text-h6 font-weight-bold">Заявка</span>
                </div>
                <v-btn icon variant="tonal" size="small" @click="model = false">
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </v-card-title>
            <v-divider />
            <v-card-text class="px-6 py-5">
                <v-text-field
                    ref="titleInput"
                    v-model="form.title"
                    label="Название"
                    placeholder="На что нужен аванс"
                    hide-details
                    class="mb-2"
                />
                <v-row dense>
                    <v-col cols="12" sm="6">
                        <v-text-field
                            v-model="form.amount"
                            type="number"
                            label="Сумма, ₽"
                            placeholder="0"
                            min="0"
                            hide-details
                        />
                    </v-col>
                    <v-col cols="12" sm="6">
                        <v-select
                            v-model="form.status_id"
                            :items="statusItems"
                            item-title="label"
                            item-value="id"
                            label="Статус"
                            hide-details
                        >
                            <template #selection>
                                <span class="d-inline-flex align-center ga-2">
                                    <span
                                        style="width:8px;height:8px;border-radius:50%;display:inline-block;flex-shrink:0"
                                        :style="dictDotStyle(store.getAdvanceStatus(form.status_id)?.color)"
                                    />
                                    <span>{{ store.getAdvanceStatus(form.status_id)?.label }}</span>
                                </span>
                            </template>
                        </v-select>
                    </v-col>
                    <v-col cols="12" sm="6">
                        <v-select
                            v-model="form.disbursement_method_id"
                            :items="methodItems"
                            item-title="label"
                            item-value="id"
                            label="Способ выдачи"
                            clearable
                            hide-details
                        />
                    </v-col>
                    <v-col cols="12" sm="6">
                        <v-select
                            v-model="form.task_ids"
                            :items="taskItems"
                            item-title="title"
                            item-value="id"
                            label="Поручения"
                            multiple
                            chips
                            clearable
                            hide-details
                        />
                    </v-col>
                </v-row>
                <v-textarea v-model="form.note" label="Комментарий" rows="2" hide-details class="mb-3" />

                <div class="skydesk-accent-panel pa-4 mb-4">
                    <div class="d-flex justify-space-between text-body-2 mb-1">
                        <span>Потрачено</span>
                        <b>{{ store.formatMoney(spent) }}</b>
                    </div>
                    <div class="d-flex justify-space-between text-body-2">
                        <span>Остаток</span>
                        <b :class="remaining < 0 ? 'text-error' : ''">{{ store.formatMoney(remaining) }}</b>
                    </div>
                </div>

                <div class="d-flex flex-wrap ga-2 mb-4">
                    <v-btn
                        size="small"
                        variant="tonal"
                        color="success"
                        :disabled="!canSettle"
                        @click="store.releaseRemainderToFree(advanceId)"
                    >
                        Остаток → свободно
                    </v-btn>
                    <v-btn
                        size="small"
                        variant="tonal"
                        color="primary"
                        :disabled="!canSettle"
                        @click="store.returnRemainderToBoss(advanceId)"
                    >
                        Вернуть боссу
                    </v-btn>
                    <v-btn
                        size="small"
                        variant="tonal"
                        color="warning"
                        :disabled="!canSettle"
                        @click="store.writeOffUnknown(advanceId)"
                    >
                        Списать как неизвестное
                    </v-btn>
                </div>

                <div class="d-flex align-center justify-space-between mb-2">
                    <h3 class="text-subtitle-2 font-weight-bold mb-0">Траты</h3>
                </div>
                <div
                    v-for="x in expenseList"
                    :key="x.id"
                    class="px-3 py-3 mb-2"
                    style="border-radius:11px;border:1px solid rgba(var(--v-border-color),var(--v-border-opacity))"
                >
                    <div class="d-flex justify-space-between align-start">
                        <div>
                            <div class="text-body-2 font-weight-bold">{{ x.description || 'Трата' }}</div>
                            <div class="text-caption text-medium-emphasis">
                                {{ store.getExpenseArticle(x.article_id)?.label || '—' }}
                                ·
                                {{ store.getSupplier(x.supplier_id)?.name || '—' }}
                            </div>
                        </div>
                        <div class="text-right">
                            <b>{{ store.formatMoney(x.amount) }}</b>
                            <div>
                                <v-btn
                                    size="x-small"
                                    variant="text"
                                    color="error"
                                    @click="store.removeExpense(x.id)"
                                >
                                    Удалить
                                </v-btn>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div
                            v-if="receiptsFor(x.id).length || (uploadingReceipt && receiptExpenseId === x.id)"
                            class="d-flex flex-wrap ga-2 mb-2"
                        >
                            <div
                                v-for="r in receiptsFor(x.id).filter(isImageReceipt)"
                                :key="r.id"
                                class="skydesk-att-item"
                            >
                                <button
                                    type="button"
                                    class="skydesk-att-thumb-btn"
                                    aria-label="Открыть чек"
                                    @click="openReceipt(r)"
                                >
                                    <img
                                        :src="r.url"
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
                                    @click="removeReceipt(x.id, r.id)"
                                >
                                    <v-icon size="14">mdi-close</v-icon>
                                </v-btn>
                            </div>
                        </div>

                        <div
                            v-for="r in receiptsFor(x.id).filter((item) => !isImageReceipt(item))"
                            :key="`file-${r.id}`"
                            class="d-flex align-center ga-2 px-3 py-2 mb-1 skydesk-accent-panel"
                        >
                            <v-icon size="16">mdi-file-outline</v-icon>
                            <button
                                type="button"
                                class="flex-grow-1 text-start text-body-2 skydesk-att-name"
                                @click="openReceipt(r)"
                            >
                                {{ r.name || r.original_name || 'Файл' }}
                            </button>
                            <v-btn icon size="x-small" variant="text" @click="removeReceipt(x.id, r.id)">
                                <v-icon size="16">mdi-delete-outline</v-icon>
                            </v-btn>
                        </div>

                        <div class="d-flex align-center ga-2 flex-wrap">
                            <v-btn
                                size="x-small"
                                variant="tonal"
                                prepend-icon="mdi-camera"
                                :loading="uploadingReceipt && receiptExpenseId === x.id"
                                @click="addReceipt(x.id)"
                            >
                                Чек / фото
                            </v-btn>
                            <div
                                v-if="uploadingReceipt && receiptExpenseId === x.id"
                                class="text-caption text-medium-emphasis"
                            >
                                Загрузка…
                            </div>
                        </div>
                    </div>
                </div>

                <v-card variant="outlined" class="pa-4 mt-2">
                    <div class="text-caption font-weight-bold mb-2">Новая трата</div>
                    <v-row dense>
                        <v-col cols="12" sm="4">
                            <v-text-field v-model="expenseDraft.amount" type="number" label="Сумма" density="compact" hide-details />
                        </v-col>
                        <v-col cols="12" sm="4">
                            <v-select
                                v-model="expenseDraft.article_id"
                                :items="articleItems"
                                item-title="label"
                                item-value="id"
                                label="Статья"
                                density="compact"
                                hide-details
                            />
                        </v-col>
                        <v-col cols="12" sm="4">
                            <v-select
                                v-model="expenseDraft.supplier_id"
                                :items="supplierItems"
                                item-title="title"
                                item-value="id"
                                label="Поставщик"
                                density="compact"
                                hide-details
                            />
                        </v-col>
                        <v-col cols="12">
                            <v-text-field v-model="expenseDraft.description" label="Описание" density="compact" hide-details />
                        </v-col>
                    </v-row>
                    <v-btn class="mt-3" color="primary" size="small" @click="addExpense">Добавить трату</v-btn>
                </v-card>

                <v-btn
                    v-if="form.task_ids?.length"
                    class="mt-4"
                    variant="text"
                    color="primary"
                    @click="openFirstTask"
                >
                    Открыть поручение →
                </v-btn>
            </v-card-text>
        </v-card>

        <input
            ref="receiptInput"
            type="file"
            class="d-none"
            multiple
            accept="image/*,.pdf"
            @change="onReceiptSelected"
        />
    </v-dialog>

    <v-dialog
        :model-value="!!previewReceipt"
        max-width="920"
        @update:model-value="(v) => { if (!v) closeReceiptPreview() }"
    >
        <v-card v-if="previewReceipt" class="pa-3">
            <div class="d-flex align-center justify-end ga-1 mb-2">
                <v-btn
                    icon
                    size="small"
                    variant="tonal"
                    :href="previewReceipt.url"
                    target="_blank"
                    rel="noopener"
                    aria-label="Открыть оригинал"
                >
                    <v-icon size="18">mdi-open-in-new</v-icon>
                </v-btn>
                <v-btn icon size="small" variant="tonal" aria-label="Закрыть" @click="closeReceiptPreview">
                    <v-icon size="18">mdi-close</v-icon>
                </v-btn>
            </div>
            <img
                :src="previewReceipt.url"
                alt=""
                class="skydesk-att-preview"
            >
        </v-card>
    </v-dialog>
</template>

<style scoped>
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

.skydesk-att-name {
    border: 0;
    background: transparent;
    padding: 0;
    color: rgb(var(--v-theme-on-surface));
    cursor: pointer;
    word-break: break-all;
}

.skydesk-att-name:hover {
    text-decoration: underline;
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
