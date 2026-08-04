<script setup>
import { computed, reactive, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import OwnerBadge from '@/Components/OwnerBadge.vue';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';
import { useWorkspaceUi } from '@/composables/useWorkspaceUi';
import { useIsAdmin } from '@/composables/useIsAdmin';
import { dictChipStyle } from '@/utils/dictColor';
import { prepareUploadFile } from '@/utils/compressImage';

const store = useSkyDeskStore();
const { openAdvance, openAdvanceCreate, openTask } = useWorkspaceUi();
const { isAdmin } = useIsAdmin();

const tab = ref('movement');
const filter = ref('all');
const showTopUp = ref(false);
const showExpense = ref(false);
const editingTopUpId = ref(null);
const editingExpenseId = ref(null);
const expenseReceiptInput = ref(null);
const uploadingExpenseReceipt = ref(false);

const topUpForm = reactive({
    title: '',
    amount: '',
    note: '',
    disbursement_method_id: null,
    occurred_at: '',
});
const expenseForm = reactive({
    amount: '',
    description: '',
    article_id: null,
    supplier_id: null,
    debit_account: 'unassigned',
    advance_id: null,
    pendingReceipts: [],
    occurred_at: '',
});

const todayIsoDate = () => {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
};

const formatTxDate = (value) => {
    const raw = String(value || '');
    const m = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (m) return `${m[3]}.${m[2]}.${m[1]}`;
    if (!raw) return '—';
    const d = new Date(raw);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('ru-RU');
};

const filters = [
    { value: 'all', label: 'Все' },
    { value: 'pending', label: 'Заявка' },
    { value: 'received', label: 'Получены' },
    { value: 'reporting', label: 'На отчёте' },
    { value: 'closed', label: 'Закрыта' },
];

const accountLabels = {
    wallet: 'Кошелёк',
    advance: 'Аванс',
    unassigned: 'Не разнесено',
};

const txTypeLabels = {
    income: 'Приход',
    expense: 'Расход',
    transfer: 'Перевод',
};

const debitAccountItems = [
    { id: 'unassigned', label: 'Пока не разносить' },
    { id: 'wallet', label: 'Кошелёк' },
];

const openAdvanceItems = computed(() =>
    store.advances.value
        .filter((a) => ['received', 'reporting'].includes(a.status_id))
        .map((a) => ({
            id: a.id,
            title: a.title || `Аванс #${a.id}`,
        })),
);

const visibleAdvances = computed(() => {
    if (filter.value === 'all') return store.advances.value;
    return store.advances.value.filter((a) => a.status_id === filter.value);
});

const summary = computed(() => {
    const pending = store.advances.value
        .filter((a) => a.status_id === 'pending')
        .reduce((s, a) => s + Number(a.amount || 0), 0);
    const received = store.advances.value
        .filter((a) => a.status_id === 'received')
        .reduce((s, a) => s + Number(a.remaining ?? a.amount ?? 0), 0);
    const reporting = store.advances.value
        .filter((a) => a.status_id === 'reporting')
        .reduce((s, a) => s + Number(a.remaining ?? a.amount ?? 0), 0);
    const closed = store.advances.value
        .filter((a) => a.status_id === 'closed')
        .reduce((s, a) => s + Number(a.amount || 0), 0);
    return [
        { label: 'Заявки', amount: store.formatMoney(pending), tone: 'orange' },
        { label: 'Получены', amount: store.formatMoney(received), tone: null },
        { label: 'На отчёте', amount: store.formatMoney(reporting), tone: null },
        { label: 'Закрыто', amount: store.formatMoney(closed), tone: 'green' },
    ];
});

const transactions = computed(() => {
    const list = [...(store.wallet.value.transactions || [])];
    return list.sort((a, b) => {
        const da = String(a.occurred_at || a.created_at || '');
        const db = String(b.occurred_at || b.created_at || '');
        if (da !== db) return db.localeCompare(da);
        return Number(b.id) - Number(a.id);
    });
});

const methodItems = computed(() => store.dictionaries.value.disbursementMethods || []);

const supplierItems = computed(() =>
    store.suppliers.value.map((s) => ({
        id: s.id,
        title: s.name || `Поставщик #${s.id}`,
    })),
);

const resetTopUpForm = () => {
    topUpForm.title = '';
    topUpForm.amount = '';
    topUpForm.note = '';
    topUpForm.disbursement_method_id = methodItems.value[0]?.id || null;
    topUpForm.occurred_at = todayIsoDate();
    editingTopUpId.value = null;
};

const editingExpenseReceipts = computed(() =>
    editingExpenseId.value ? store.receiptsForExpense(editingExpenseId.value) || [] : [],
);

const resetExpenseForm = () => {
    expenseForm.amount = '';
    expenseForm.description = '';
    expenseForm.article_id = store.dictionaries.value.expenseArticles?.[0]?.id || null;
    expenseForm.supplier_id = null;
    expenseForm.debit_account = 'unassigned';
    expenseForm.advance_id = null;
    expenseForm.pendingReceipts = [];
    expenseForm.occurred_at = todayIsoDate();
    editingExpenseId.value = null;
};

const openTopUpCreate = () => {
    resetTopUpForm();
    showTopUp.value = true;
};

const openExpenseCreate = () => {
    resetExpenseForm();
    showExpense.value = true;
};

const createAdvance = () => {
    openAdvanceCreate({
        title: '',
        amount: 0,
        status_id: 'pending',
    });
};

const submitTopUp = () => {
    const amount = Number(topUpForm.amount);
    if (!amount || !topUpForm.disbursement_method_id) {
        window.alert('Укажите сумму и способ получения.');
        return;
    }
    const payload = {
        amount,
        note: topUpForm.note,
        title: topUpForm.title,
        disbursement_method_id: topUpForm.disbursement_method_id,
        occurred_at: topUpForm.occurred_at || todayIsoDate(),
    };
    if (editingTopUpId.value) {
        store.updateTopUp(editingTopUpId.value, payload);
    } else {
        store.topUpWallet(payload);
    }
    showTopUp.value = false;
    resetTopUpForm();
};

const onExpenseReceiptsSelected = async (event) => {
    const files = [...(event.target.files || [])];
    event.target.value = '';
    if (!files.length) return;
    uploadingExpenseReceipt.value = true;
    try {
        for (const raw of files) {
            const prepared = await prepareUploadFile(raw);
            if (editingExpenseId.value) {
                store.addReceipt(editingExpenseId.value, prepared.file);
            } else {
                expenseForm.pendingReceipts.push(prepared.file);
            }
        }
    } finally {
        uploadingExpenseReceipt.value = false;
    }
};

const removePendingReceipt = (idx) => {
    expenseForm.pendingReceipts.splice(idx, 1);
};

const submitExpense = () => {
    const amount = Number(expenseForm.amount);
    if (!amount || !expenseForm.article_id) {
        window.alert('Нужны сумма и статья.');
        return;
    }
    if (expenseForm.debit_account === 'advance' && !expenseForm.advance_id) {
        window.alert('Выберите аванс для списания.');
        return;
    }
    const payload = {
        amount,
        description: expenseForm.description,
        article_id: expenseForm.article_id,
        supplier_id: expenseForm.supplier_id || null,
        debit_account: expenseForm.debit_account === 'advance' ? 'advance' : expenseForm.debit_account,
        advance_id: expenseForm.debit_account === 'advance' ? expenseForm.advance_id : null,
        receipts: expenseForm.pendingReceipts,
        occurred_at: expenseForm.occurred_at || todayIsoDate(),
    };
    if (editingExpenseId.value) {
        store.updateExpense(editingExpenseId.value, {
            amount: payload.amount,
            description: payload.description,
            article_id: payload.article_id,
            supplier_id: payload.supplier_id,
            debit_account: payload.debit_account,
            occurred_at: payload.occurred_at,
        });
    } else if (payload.advance_id) {
        store.addExpense(payload.advance_id, payload);
    } else {
        store.addExpense(null, payload);
    }
    showExpense.value = false;
    resetExpenseForm();
};

const openExpenseEditor = (expenseId) => {
    const expense = store.expenses.value.find((e) => String(e.id) === String(expenseId));
    if (!expense) return;
    if (expense.advance_id) {
        openAdvance(expense.advance_id);
        return;
    }
    editingExpenseId.value = expense.id;
    expenseForm.amount = expense.amount;
    expenseForm.description = expense.description || '';
    expenseForm.article_id = expense.article_id;
    expenseForm.supplier_id = expense.supplier_id;
    expenseForm.debit_account = expense.debit_account || 'unassigned';
    expenseForm.advance_id = null;
    expenseForm.pendingReceipts = [];
    expenseForm.occurred_at = expense.occurred_at || todayIsoDate();
    showExpense.value = true;
};

const openTopUpEditor = (tx) => {
    editingTopUpId.value = tx.id;
    topUpForm.title = tx.meta?.title || tx.title || '';
    topUpForm.amount = Math.abs(Number(tx.amount));
    topUpForm.note = tx.meta?.note || '';
    topUpForm.disbursement_method_id = tx.meta?.disbursement_method_id || methodItems.value[0]?.id || null;
    topUpForm.occurred_at = tx.occurred_at || todayIsoDate();
    showTopUp.value = true;
};

const deleteEditingTopUp = () => {
    if (!editingTopUpId.value) return;
    if (!window.confirm('Удалить эту операцию? Баланс будет скорректирован.')) return;
    store.removeTransaction(editingTopUpId.value);
    showTopUp.value = false;
    resetTopUpForm();
};

const onTransactionClick = (tx) => {
    if (tx.advance_id) {
        openAdvance(tx.advance_id);
        return;
    }
    if (tx.type === 'expense' && tx.expense_id) {
        openExpenseEditor(tx.expense_id);
        return;
    }
    if (tx.type === 'income' && tx.account === 'wallet') {
        openTopUpEditor(tx);
    }
};

const isClickableTx = (tx) =>
    !!tx.advance_id
    || (tx.type === 'income' && tx.account === 'wallet')
    || (tx.type === 'expense' && !!tx.expense_id);

const canDeleteTx = (tx) =>
    (tx.type === 'income' && tx.account === 'wallet')
    || (tx.type === 'expense' && !!tx.expense_id);

const deleteTx = (tx) => {
    if (!window.confirm('Удалить эту операцию? Баланс будет скорректирован.')) return;
    store.removeTransaction(tx.id);
};

const taskLabel = (adv) => {
    const ids = adv.task_ids?.length ? adv.task_ids : (adv.task_id ? [adv.task_id] : []);
    return ids;
};

const txSubtitle = (tx) => {
    const type = txTypeLabels[tx.type] || tx.type;
    const account = accountLabels[tx.account] || tx.account;
    return `${type} · ${account}`;
};

const wallet = computed(() => store.wallet.value);
</script>

<template>
    <AppLayout
        title="Финансы"
        subtitle="Кошелёк, авансы и реестр движения."
        :show-fab="false"
    >
        <template #actions>
            <v-btn
                variant="tonal"
                prepend-icon="mdi-wallet-plus"
                size="small"
                class="flex-grow-1 flex-md-grow-0"
                @click="openTopUpCreate"
            >
                Приход
            </v-btn>
            <v-btn
                variant="tonal"
                prepend-icon="mdi-receipt-text-plus"
                size="small"
                class="flex-grow-1 flex-md-grow-0"
                @click="openExpenseCreate"
            >
                Расход
            </v-btn>
            <v-btn
                color="primary"
                prepend-icon="mdi-cash-plus"
                size="small"
                class="flex-grow-1 flex-md-grow-0"
                @click="createAdvance"
            >
                <span class="d-md-none">Заявка</span>
                <span class="d-none d-md-inline">Заявка на аванс</span>
            </v-btn>
        </template>

        <v-card class="pa-5 mb-5 skydesk-accent-panel">
            <div class="text-caption font-weight-bold text-primary mb-1">На руках</div>
            <div class="text-h4 font-weight-bold" style="font-family:Fraunces,Georgia,serif;letter-spacing:-.03em">
                {{ store.formatMoney(wallet.on_hand ?? wallet.balance) }}
            </div>
            <div class="d-flex flex-wrap ga-4 mt-3">
                <div>
                    <div class="text-caption text-medium-emphasis">Кошелёк</div>
                    <div class="text-h6 font-weight-bold">{{ store.formatMoney(wallet.wallet ?? wallet.free) }}</div>
                </div>
                <div>
                    <div class="text-caption text-medium-emphasis">В авансах</div>
                    <div class="text-h6 font-weight-bold">{{ store.formatMoney(wallet.in_advances) }}</div>
                </div>
                <div v-if="Number(wallet.unassigned) > 0">
                    <div class="text-caption text-medium-emphasis">Не разнесено</div>
                    <div class="text-h6 font-weight-bold">{{ store.formatMoney(wallet.unassigned) }}</div>
                </div>
            </div>
        </v-card>

        <v-tabs v-model="tab" class="mb-4" color="primary">
            <v-tab value="movement">Движение</v-tab>
            <v-tab value="advances">Авансы</v-tab>
        </v-tabs>

        <div v-if="tab === 'movement'">
            <v-card class="pa-5">
                <h3 class="text-subtitle-2 font-weight-bold mb-3">Реестр</h3>
                <div v-if="!transactions.length" class="text-caption text-medium-emphasis">Пока пусто.</div>
                <div
                    v-for="tx in transactions"
                    :key="tx.id"
                    class="d-flex align-start ga-3 py-2"
                    :style="{
                        borderBottom: '1px solid rgba(var(--v-border-color),var(--v-border-opacity))',
                        cursor: isClickableTx(tx) ? 'pointer' : 'default',
                    }"
                    @click="isClickableTx(tx) && onTransactionClick(tx)"
                >
                    <div class="text-body-2 font-weight-bold text-no-wrap" style="min-width:5.5rem">
                        {{ formatTxDate(tx.occurred_at || tx.created_at) }}
                    </div>
                    <div class="min-w-0 flex-grow-1 pe-2">
                        <div class="text-body-2 font-weight-bold text-truncate">{{ tx.title || txTypeLabels[tx.type] || tx.type }}</div>
                        <div class="text-caption text-medium-emphasis">{{ txSubtitle(tx) }}</div>
                    </div>
                    <div
                        class="font-weight-bold text-no-wrap"
                        :class="Number(tx.amount) < 0 ? 'text-error' : Number(tx.amount) > 0 ? 'text-success' : ''"
                    >
                        {{ Number(tx.amount) > 0 ? '+' : '' }}{{ store.formatMoney(tx.amount) }}
                    </div>
                    <v-btn
                        v-if="canDeleteTx(tx)"
                        icon
                        variant="text"
                        size="x-small"
                        color="error"
                        aria-label="Удалить"
                        @click.stop="deleteTx(tx)"
                    >
                        <v-icon size="16">mdi-delete-outline</v-icon>
                    </v-btn>
                </div>
            </v-card>
        </div>

        <div v-else>
            <div class="d-flex flex-wrap ga-3 mb-5">
                <v-card
                    v-for="s in summary"
                    :key="s.label"
                    class="pa-4"
                    style="flex:1 1 140px;min-width:140px"
                >
                    <div class="text-caption text-medium-emphasis mb-1">{{ s.label }}</div>
                    <div
                        class="text-h6 font-weight-bold"
                        :style="s.tone === 'orange' ? 'color:#E67E22' : s.tone === 'green' ? 'color:#37A878' : ''"
                    >
                        {{ s.amount }}
                    </div>
                </v-card>
            </div>

            <div class="d-flex ga-2 mb-4 flex-wrap">
                <v-chip
                    v-for="f in filters"
                    :key="f.value"
                    :color="filter === f.value ? 'primary' : undefined"
                    :variant="filter === f.value ? 'flat' : 'tonal'"
                    @click="filter = f.value"
                >
                    {{ f.label }}
                </v-chip>
            </div>

            <v-card>
                <div
                    v-for="adv in visibleAdvances"
                    :key="adv.id"
                    class="d-flex align-center ga-3 px-5 py-4 skydesk-task"
                    style="cursor:pointer;border-bottom:1px solid rgba(var(--v-border-color),var(--v-border-opacity))"
                    @click="openAdvance(adv.id)"
                >
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex align-center ga-2">
                            <div class="text-body-1 font-weight-bold text-truncate">{{ adv.title || 'Без названия' }}</div>
                            <OwnerBadge :show="isAdmin" :user="adv.user" />
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            {{ adv.note || 'Без описания' }}
                            <template v-for="tid in taskLabel(adv)" :key="tid">
                                ·
                                <a
                                    class="text-primary"
                                    style="text-decoration:none;font-weight:700"
                                    @click.stop="openTask(tid)"
                                >
                                    поручение
                                </a>
                            </template>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-weight-bold">{{ store.formatMoney(adv.amount) }}</div>
                        <v-chip
                            size="x-small"
                            class="skydesk-pill mt-1"
                            variant="tonal"
                            :style="dictChipStyle(store.getAdvanceStatus(adv.status_id)?.color)"
                        >
                            {{ store.getAdvanceStatus(adv.status_id)?.label }}
                        </v-chip>
                    </div>
                </div>
                <div v-if="!visibleAdvances.length" class="pa-5 text-caption text-medium-emphasis">Авансов нет.</div>
            </v-card>
        </div>

        <v-dialog
            :model-value="showTopUp"
            max-width="420"
            @update:model-value="(v) => { showTopUp = v; if (!v) resetTopUpForm(); }"
        >
            <v-card class="pa-5">
                <h3 class="text-h6 font-weight-bold mb-3">
                    {{ editingTopUpId ? 'Редактировать приход' : 'Приход на кошелёк' }}
                </h3>
                <v-text-field v-model="topUpForm.title" label="Название" class="mb-2" />
                <v-text-field v-model="topUpForm.amount" type="number" label="Сумма, ₽" min="0" class="mb-2" />
                <v-text-field
                    v-model="topUpForm.occurred_at"
                    type="date"
                    label="Дата"
                    class="mb-2"
                />
                <v-select
                    v-model="topUpForm.disbursement_method_id"
                    :items="methodItems"
                    item-title="label"
                    item-value="id"
                    label="Способ получения"
                    class="mb-2"
                />
                <v-text-field v-model="topUpForm.note" label="Комментарий" class="mb-3" />
                <div class="d-flex justify-space-between ga-2">
                    <v-btn
                        v-if="editingTopUpId"
                        variant="text"
                        color="error"
                        @click="deleteEditingTopUp"
                    >
                        Удалить
                    </v-btn>
                    <div class="d-flex justify-end ga-2 flex-grow-1">
                        <v-btn variant="text" @click="showTopUp = false; resetTopUpForm()">Отмена</v-btn>
                        <v-btn color="primary" @click="submitTopUp">
                            {{ editingTopUpId ? 'Сохранить' : 'Записать' }}
                        </v-btn>
                    </div>
                </div>
            </v-card>
        </v-dialog>

        <v-dialog
            :model-value="showExpense"
            max-width="480"
            @update:model-value="(v) => { showExpense = v; if (!v) resetExpenseForm(); }"
        >
            <v-card class="pa-5">
                <h3 class="text-h6 font-weight-bold mb-3">
                    {{ editingExpenseId ? 'Редактировать расход' : 'Расход' }}
                </h3>
                <v-text-field v-model="expenseForm.amount" type="number" label="Сумма, ₽" min="0" class="mb-2" />
                <v-text-field
                    v-model="expenseForm.occurred_at"
                    type="date"
                    label="Дата"
                    class="mb-2"
                />
                <v-select
                    v-model="expenseForm.article_id"
                    :items="store.dictionaries.value.expenseArticles"
                    item-title="label"
                    item-value="id"
                    label="Статья"
                    class="mb-2"
                />
                <v-select
                    v-model="expenseForm.supplier_id"
                    :items="supplierItems"
                    item-title="title"
                    item-value="id"
                    label="Поставщик"
                    class="mb-2"
                    clearable
                />
                <v-select
                    v-if="!editingExpenseId"
                    v-model="expenseForm.debit_account"
                    :items="[
                        ...debitAccountItems,
                        ...(openAdvanceItems.length ? [{ id: 'advance', label: 'Аванс' }] : []),
                    ]"
                    item-title="label"
                    item-value="id"
                    label="Счёт списания"
                    class="mb-2"
                />
                <v-select
                    v-if="!editingExpenseId && expenseForm.debit_account === 'advance'"
                    v-model="expenseForm.advance_id"
                    :items="openAdvanceItems"
                    item-title="title"
                    item-value="id"
                    label="Аванс"
                    class="mb-2"
                />
                <v-select
                    v-if="editingExpenseId"
                    v-model="expenseForm.debit_account"
                    :items="debitAccountItems"
                    item-title="label"
                    item-value="id"
                    label="Счёт списания"
                    class="mb-2"
                />
                <v-text-field v-model="expenseForm.description" label="Название / описание" class="mb-2" />
                <div class="d-flex flex-wrap align-center ga-2 mb-3">
                    <v-btn
                        size="small"
                        variant="tonal"
                        prepend-icon="mdi-paperclip"
                        :loading="uploadingExpenseReceipt"
                        @click="expenseReceiptInput?.click()"
                    >
                        Файл / фото
                    </v-btn>
                    <v-chip
                        v-for="(f, idx) in expenseForm.pendingReceipts"
                        :key="`${f.name}-${idx}`"
                        size="small"
                        closable
                        @click:close="removePendingReceipt(idx)"
                    >
                        {{ f.name || 'файл' }}
                    </v-chip>
                    <template v-if="editingExpenseId">
                        <v-chip
                            v-for="r in editingExpenseReceipts"
                            :key="r.id"
                            size="small"
                            closable
                            @click:close="store.removeReceipt(editingExpenseId, r.id)"
                        >
                            {{ r.name || r.original_name || 'файл' }}
                        </v-chip>
                    </template>
                </div>
                <input
                    ref="expenseReceiptInput"
                    type="file"
                    class="d-none"
                    multiple
                    accept="image/*,.pdf"
                    @change="onExpenseReceiptsSelected"
                />
                <div class="d-flex justify-space-between ga-2">
                    <v-btn
                        v-if="editingExpenseId"
                        variant="text"
                        color="error"
                        @click="store.removeExpense(editingExpenseId); showExpense = false; resetExpenseForm()"
                    >
                        Удалить
                    </v-btn>
                    <div class="d-flex justify-end ga-2 flex-grow-1">
                        <v-btn variant="text" @click="showExpense = false; resetExpenseForm()">Отмена</v-btn>
                        <v-btn color="primary" @click="submitExpense">
                            {{ editingExpenseId ? 'Сохранить' : 'Записать' }}
                        </v-btn>
                    </div>
                </div>
            </v-card>
        </v-dialog>
    </AppLayout>
</template>
