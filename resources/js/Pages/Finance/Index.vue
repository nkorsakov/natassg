<script setup>
import { computed, reactive, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import OwnerBadge from '@/Components/OwnerBadge.vue';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';
import { useWorkspaceUi } from '@/composables/useWorkspaceUi';
import { useIsAdmin } from '@/composables/useIsAdmin';
import { dictChipStyle } from '@/utils/dictColor';
import { prepareUploadFile } from '@/utils/compressImage';
import DateTimeFields from '@/Components/DateTimeFields.vue';
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
    { value: 'approved', label: 'Утвердили' },
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
        .filter((a) => a.status_id === 'reporting')
        .map((a) => ({
            id: a.id,
            title: a.title || `Аванс #${a.id}`,
        })),
);

const visibleAdvances = computed(() => {
    const list = filter.value === 'all'
        ? [...store.advances.value]
        : store.advances.value.filter((a) => a.status_id === filter.value);
    return list.sort((a, b) => {
        const da = String(a.needed_at || a.issued_at || a.created_at || '');
        const db = String(b.needed_at || b.issued_at || b.created_at || '');
        if (da !== db) return db.localeCompare(da);
        return Number(b.id) - Number(a.id);
    });
});

const summary = computed(() => {
    const pending = store.advances.value
        .filter((a) => a.status_id === 'pending')
        .reduce((s, a) => s + Number(a.amount || 0), 0);
    const approved = store.advances.value
        .filter((a) => a.status_id === 'approved')
        .reduce((s, a) => s + Number(a.amount || 0), 0);
    const reporting = store.advances.value
        .filter((a) => a.status_id === 'reporting')
        .reduce((s, a) => s + Number(a.remaining ?? a.amount ?? 0), 0);
    const closed = store.advances.value
        .filter((a) => a.status_id === 'closed')
        .reduce((s, a) => s + Number(a.amount || 0), 0);
    return [
        { label: 'Заявки', amount: store.formatMoney(pending), tone: 'orange' },
        { label: 'Утвердили', amount: store.formatMoney(approved), tone: null },
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
        <v-card class="pa-4 mb-4 skydesk-accent-panel">
            <div class="d-flex align-start justify-space-between ga-3 flex-wrap">
                <div class="min-w-0">
                    <div class="text-caption font-weight-bold text-primary mb-0">На руках</div>
                    <div
                        class="text-h5 font-weight-bold"
                        style="font-family:Fraunces,Georgia,serif;letter-spacing:-.03em;line-height:1.2"
                    >
                        {{ store.formatMoney(wallet.on_hand ?? wallet.balance) }}
                    </div>
                    <div class="d-flex flex-wrap ga-3 mt-2 text-caption">
                        <span>
                            <span class="text-medium-emphasis">Кошелёк</span>
                            <b class="ms-1">{{ store.formatMoney(wallet.wallet ?? wallet.free) }}</b>
                        </span>
                        <span>
                            <span class="text-medium-emphasis">В авансах</span>
                            <b class="ms-1">{{ store.formatMoney(wallet.in_advances) }}</b>
                        </span>
                        <span v-if="Number(wallet.unassigned) > 0">
                            <span class="text-medium-emphasis">Не разнесено</span>
                            <b class="ms-1">{{ store.formatMoney(wallet.unassigned) }}</b>
                        </span>
                    </div>
                </div>
                <div class="d-flex align-center ga-2 flex-shrink-0 flex-wrap">
                    <v-btn
                        variant="tonal"
                        prepend-icon="mdi-wallet-plus"
                        size="small"
                        @click="openTopUpCreate"
                    >
                        Приход
                    </v-btn>
                    <v-btn
                        variant="tonal"
                        prepend-icon="mdi-receipt-text-plus"
                        size="small"
                        @click="openExpenseCreate"
                    >
                        Расход
                    </v-btn>
                    <v-btn
                        color="primary"
                        prepend-icon="mdi-cash-plus"
                        size="small"
                        @click="createAdvance"
                    >
                        Заявка
                    </v-btn>
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
                </div>
            </v-card>
        </div>

        <div v-else>
            <div class="d-flex flex-wrap ga-4 mb-4 text-caption">
                <span
                    v-for="s in summary"
                    :key="s.label"
                >
                    <span class="text-medium-emphasis">{{ s.label }}</span>
                    <b
                        class="ms-1"
                        :style="s.tone === 'orange' ? 'color:#E67E22' : s.tone === 'green' ? 'color:#37A878' : ''"
                    >{{ s.amount }}</b>
                </span>
            </div>

            <div class="d-flex ga-2 mb-4 flex-wrap">
                <v-chip
                    v-for="f in filters"
                    :key="f.value"
                    :color="filter === f.value ? 'primary' : undefined"
                    :variant="filter === f.value ? 'flat' : 'tonal'"
                    size="small"
                    @click="filter = f.value"
                >
                    {{ f.label }}
                </v-chip>
            </div>

            <v-card>
                <div
                    v-for="adv in visibleAdvances"
                    :key="adv.id"
                    class="d-flex align-start ga-3 px-5 py-4 skydesk-task"
                    style="cursor:pointer;border-bottom:1px solid rgba(var(--v-border-color),var(--v-border-opacity))"
                    @click="openAdvance(adv.id)"
                >
                    <div class="text-body-2 font-weight-bold text-no-wrap" style="min-width:5.5rem">
                        {{ formatTxDate(adv.needed_at || adv.issued_at || adv.created_at) }}
                    </div>
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
                <DateTimeFields
                    v-model="topUpForm.occurred_at"
                    date-label="Дата"
                    all-day
                    hide-details
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
                <DateTimeFields
                    v-model="expenseForm.occurred_at"
                    date-label="Дата"
                    all-day
                    hide-details
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
