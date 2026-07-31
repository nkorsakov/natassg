<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useDisplay } from 'vuetify';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';
import { prepareUploadFile } from '@/utils/compressImage';

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

const advance = computed(() => (props.advanceId ? store.getAdvance(props.advanceId) : null));
const expenseList = computed(() =>
    props.advanceId ? store.expensesForAdvance(props.advanceId) : [],
);
const statusItems = computed(() => store.dictionaries.value.advanceStatuses);
const taskItems = computed(() =>
    store.tasks.value.map((t) => ({ id: t.id, title: t.title })),
);

const form = reactive({
    title: '',
    amount: 0,
    status_id: 'pending',
    task_id: null,
    note: '',
});

const expenseDraft = reactive({ amount: '', description: '' });

watch(
    () => [model.value, props.advanceId],
    () => {
        if (!model.value || !advance.value) return;
        form.title = advance.value.title;
        form.amount = advance.value.amount;
        form.status_id = advance.value.status_id;
        form.task_id = advance.value.task_id;
        form.note = advance.value.note || '';
        expenseDraft.amount = '';
        expenseDraft.description = '';
    },
    { immediate: true },
);

watch(
    form,
    () => {
        if (!model.value || !props.advanceId) return;
        store.updateAdvance(props.advanceId, {
            title: form.title.trim() || advance.value.title,
            amount: Number(form.amount) || 0,
            status_id: form.status_id,
            task_id: form.task_id,
            note: form.note,
        });
    },
    { deep: true },
);

const spent = computed(() => store.advanceSpent(props.advanceId));
const remaining = computed(() => store.advanceRemaining(props.advanceId));

const addExpense = () => {
    const amount = Number(expenseDraft.amount);
    if (!amount) return;
    store.addExpense(props.advanceId, {
        amount,
        description: expenseDraft.description,
    });
    expenseDraft.amount = '';
    expenseDraft.description = '';
};

const addReceipt = (expenseId) => {
    receiptExpenseId.value = expenseId;
    receiptInput.value?.click();
};

const onReceiptSelected = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file || !receiptExpenseId.value) return;
    uploadingReceipt.value = true;
    try {
        const prepared = await prepareUploadFile(file);
        store.addReceipt(receiptExpenseId.value, prepared.file);
    } finally {
        uploadingReceipt.value = false;
        receiptExpenseId.value = null;
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
                <v-text-field v-model="form.title" label="Название" class="mb-2" />
                <v-row dense>
                    <v-col cols="12" sm="6">
                        <v-text-field v-model.number="form.amount" type="number" label="Сумма, ₽" />
                    </v-col>
                    <v-col cols="12" sm="6">
                        <v-select
                            v-model="form.status_id"
                            :items="statusItems"
                            item-title="label"
                            item-value="id"
                            label="Статус"
                        />
                    </v-col>
                    <v-col cols="12">
                        <v-select
                            v-model="form.task_id"
                            :items="taskItems"
                            item-title="title"
                            item-value="id"
                            label="Поручение (необязательно)"
                            clearable
                        />
                    </v-col>
                </v-row>
                <v-textarea v-model="form.note" label="Комментарий" rows="2" class="mb-3" />

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
                        :disabled="remaining <= 0 || advance.status_id === 'closed'"
                        @click="store.returnRemainderToWallet(advanceId)"
                    >
                        Остаток в кошелёк
                    </v-btn>
                    <v-btn
                        size="small"
                        variant="tonal"
                        color="warning"
                        :disabled="remaining <= 0 || advance.status_id === 'closed'"
                        @click="store.zeroAsUnknown(advanceId)"
                    >
                        Обнулить как неизвестное
                    </v-btn>
                    <v-btn
                        size="small"
                        variant="tonal"
                        color="error"
                        :disabled="remaining >= 0 || advance.status_id === 'closed'"
                        @click="store.recordOverspend(advanceId)"
                    >
                        Зафиксировать перерасход
                    </v-btn>
                    <v-btn
                        size="small"
                        color="primary"
                        :disabled="advance.status_id === 'closed'"
                        @click="store.closeAdvanceWithSettlement(advanceId)"
                    >
                        Закрыть с расчётом
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
                    <div class="d-flex justify-space-between">
                        <div class="text-body-2 font-weight-bold">{{ x.description || 'Трата' }}</div>
                        <b>{{ store.formatMoney(x.amount) }}</b>
                    </div>
                    <div class="d-flex flex-wrap ga-2 mt-2 align-center">
                        <v-chip
                            v-for="r in store.receiptsForExpense(x.id)"
                            :key="r.id"
                            size="small"
                            variant="tonal"
                            prepend-icon="mdi-receipt"
                            :href="r.url"
                            tag="a"
                            target="_blank"
                            rel="noopener"
                        >
                            {{ r.name }}
                        </v-chip>
                        <v-btn
                            size="x-small"
                            variant="tonal"
                            prepend-icon="mdi-camera"
                            :loading="uploadingReceipt && receiptExpenseId === x.id"
                            @click="addReceipt(x.id)"
                        >
                            Чек
                        </v-btn>
                    </div>
                </div>

                <v-card variant="outlined" class="pa-4 mt-2">
                    <div class="text-caption font-weight-bold mb-2">Новая трата</div>
                    <v-row dense>
                        <v-col cols="5">
                            <v-text-field v-model="expenseDraft.amount" type="number" label="Сумма" density="compact" hide-details />
                        </v-col>
                        <v-col cols="7">
                            <v-text-field v-model="expenseDraft.description" label="Описание" density="compact" hide-details />
                        </v-col>
                    </v-row>
                    <v-btn class="mt-3" color="primary" size="small" @click="addExpense">Добавить трату</v-btn>
                </v-card>

                <v-btn
                    v-if="form.task_id"
                    class="mt-4"
                    variant="text"
                    color="primary"
                    @click="emit('open-task', form.task_id)"
                >
                    Открыть поручение →
                </v-btn>
            </v-card-text>
        </v-card>

        <input
            ref="receiptInput"
            type="file"
            class="d-none"
            accept="image/*,.pdf"
            @change="onReceiptSelected"
        />
    </v-dialog>
</template>
