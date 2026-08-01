<script setup>
import { computed, reactive, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';
import { useWorkspaceUi } from '@/composables/useWorkspaceUi';
import { dictChipStyle } from '@/utils/dictColor';

const store = useSkyDeskStore();
const { openAdvance, openTask } = useWorkspaceUi();

const filter = ref('all');
const showTopUp = ref(false);
const showFreeExpense = ref(false);
const editingTopUpId = ref(null);
const editingExpenseId = ref(null);

const topUpForm = reactive({
    title: '',
    amount: '',
    note: '',
    disbursement_method_id: null,
});
const freeExpense = reactive({
    amount: '',
    description: '',
    article_id: null,
    supplier_id: null,
});

const filters = [
    { value: 'all', label: 'Все' },
    { value: 'pending', label: 'На согласовании' },
    { value: 'approved', label: 'Одобрено' },
    { value: 'issued', label: 'Выдано' },
    { value: 'reporting', label: 'На отчёте' },
    { value: 'closed', label: 'Закрыто' },
];

const txTypeLabels = {
    topup: 'Пополнение',
    issue: 'Выдача',
    expense: 'Трата',
    return: 'Возврат',
    writeoff: 'Списание',
    amount_adjust: 'Корректировка',
    release: 'В свободно',
};

const visible = computed(() => {
    if (filter.value === 'all') return store.advances.value;
    return store.advances.value.filter((a) => a.status_id === filter.value);
});

const summary = computed(() => {
    const pending = store.advances.value
        .filter((a) => a.status_id === 'pending')
        .reduce((s, a) => s + Number(a.amount || 0), 0);
    const waiting = store.advances.value
        .filter((a) => a.status_id === 'approved')
        .reduce((s, a) => s + Number(a.amount || 0), 0);
    const inTurnover = store.advances.value
        .filter((a) => ['issued', 'reporting'].includes(a.status_id))
        .reduce((s, a) => s + Number(a.remaining ?? a.amount ?? 0), 0);
    const closed = store.advances.value
        .filter((a) => a.status_id === 'closed')
        .reduce((s, a) => s + Number(a.amount || 0), 0);
    return [
        { label: 'На согласовании', amount: store.formatMoney(pending), tone: 'orange' },
        { label: 'Ждут забрать', amount: store.formatMoney(waiting), tone: null },
        { label: 'В обороте', amount: store.formatMoney(inTurnover), tone: null },
        { label: 'Закрыто', amount: store.formatMoney(closed), tone: 'green' },
    ];
});

const transactions = computed(() => store.wallet.value.transactions || []);

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
    editingTopUpId.value = null;
};

const resetFreeExpenseForm = () => {
    freeExpense.amount = '';
    freeExpense.description = '';
    freeExpense.article_id = store.dictionaries.value.expenseArticles?.[0]?.id || null;
    freeExpense.supplier_id = null;
    editingExpenseId.value = null;
};

const openTopUpCreate = () => {
    resetTopUpForm();
    showTopUp.value = true;
};

const openFreeExpenseCreate = () => {
    resetFreeExpenseForm();
    showFreeExpense.value = true;
};

const createAdvance = async () => {
    const adv = await store.createAdvance({
        title: '',
        amount: 0,
        status_id: 'pending',
    });
    if (adv?.id) openAdvance(adv.id);
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
    };
    if (editingTopUpId.value) {
        store.updateTopUp(editingTopUpId.value, payload);
    } else {
        store.topUpWallet(payload);
    }
    showTopUp.value = false;
    resetTopUpForm();
};

const submitFreeExpense = () => {
    const amount = Number(freeExpense.amount);
    if (!amount || !freeExpense.article_id || !freeExpense.supplier_id) {
        window.alert('Нужны сумма, статья и поставщик.');
        return;
    }
    const payload = {
        amount,
        description: freeExpense.description,
        article_id: freeExpense.article_id,
        supplier_id: freeExpense.supplier_id,
    };
    if (editingExpenseId.value) {
        store.updateExpense(editingExpenseId.value, payload);
    } else {
        store.addExpense(null, payload);
    }
    showFreeExpense.value = false;
    resetFreeExpenseForm();
};

const openExpenseEditor = (expenseId) => {
    const expense = store.expenses.value.find((e) => String(e.id) === String(expenseId));
    if (!expense) return;
    if (expense.advance_id) {
        openAdvance(expense.advance_id);
        return;
    }
    editingExpenseId.value = expense.id;
    freeExpense.amount = expense.amount;
    freeExpense.description = expense.description || '';
    freeExpense.article_id = expense.article_id;
    freeExpense.supplier_id = expense.supplier_id;
    showFreeExpense.value = true;
};

const openTopUpEditor = (tx) => {
    editingTopUpId.value = tx.id;
    topUpForm.title = tx.meta?.title || tx.title || '';
    topUpForm.amount = Math.abs(Number(tx.amount));
    topUpForm.note = tx.meta?.note || '';
    topUpForm.disbursement_method_id = tx.meta?.disbursement_method_id || methodItems.value[0]?.id || null;
    showTopUp.value = true;
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
    if (tx.type === 'topup') {
        openTopUpEditor(tx);
    }
};

const isClickableTx = (tx) =>
    !!tx.advance_id || tx.type === 'topup' || (tx.type === 'expense' && !!tx.expense_id);

const taskLabel = (adv) => {
    const ids = adv.task_ids?.length ? adv.task_ids : (adv.task_id ? [adv.task_id] : []);
    return ids;
};

const txSubtitle = (tx) => {
    const type = txTypeLabels[tx.type] || tx.type;
    const when = tx.created_at ? new Date(tx.created_at).toLocaleString('ru-RU') : '';
    return `${type} · ${when}`;
};
</script>

<template>
    <AppLayout
        title="Финансы"
        subtitle="Кошелёк, авансы, свободные траты и движение."
        :show-fab="false"
    >
        <v-card class="pa-5 mb-5 skydesk-accent-panel">
            <div class="text-caption font-weight-bold text-primary mb-1">На руках</div>
            <div class="text-h4 font-weight-bold" style="font-family:Fraunces,Georgia,serif;letter-spacing:-.03em">
                {{ store.formatMoney(store.wallet.value.balance) }}
            </div>
            <div class="d-flex flex-wrap ga-4 mt-3">
                <div>
                    <div class="text-caption text-medium-emphasis">Свободно</div>
                    <div class="text-h6 font-weight-bold">{{ store.formatMoney(store.wallet.value.free) }}</div>
                </div>
                <div>
                    <div class="text-caption text-medium-emphasis">В авансах</div>
                    <div class="text-h6 font-weight-bold">{{ store.formatMoney(store.wallet.value.in_advances) }}</div>
                </div>
            </div>
            <div class="d-flex flex-wrap ga-2 mt-4">
                <v-btn
                    variant="tonal"
                    prepend-icon="mdi-wallet-plus"
                    size="small"
                    class="flex-grow-1 flex-sm-grow-0"
                    @click="openTopUpCreate"
                >
                    Приход
                </v-btn>
                <v-btn
                    variant="tonal"
                    prepend-icon="mdi-receipt-text-plus"
                    size="small"
                    class="flex-grow-1 flex-sm-grow-0"
                    @click="openFreeExpenseCreate"
                >
                    Трата
                </v-btn>
                <v-btn
                    color="primary"
                    prepend-icon="mdi-cash-plus"
                    size="small"
                    class="flex-grow-1 flex-sm-grow-0"
                    @click="createAdvance"
                >
                    <span class="d-sm-none">Заявка</span>
                    <span class="d-none d-sm-inline">Заявка на аванс</span>
                </v-btn>
            </div>

            <v-divider class="my-4" />

            <h3 class="text-subtitle-2 font-weight-bold mb-3">История</h3>
            <div v-if="!transactions.length" class="text-caption text-medium-emphasis">Пока пусто.</div>
            <div
                v-for="tx in transactions"
                :key="tx.id"
                class="d-flex justify-space-between py-2"
                :style="{
                    borderBottom: '1px solid rgba(var(--v-border-color),var(--v-border-opacity))',
                    cursor: isClickableTx(tx) ? 'pointer' : 'default',
                }"
                @click="isClickableTx(tx) && onTransactionClick(tx)"
            >
                <div class="min-w-0 pe-3">
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
                v-for="adv in visible"
                :key="adv.id"
                class="d-flex align-center ga-3 px-5 py-4 skydesk-task"
                style="cursor:pointer;border-bottom:1px solid rgba(var(--v-border-color),var(--v-border-opacity))"
                @click="openAdvance(adv.id)"
            >
                <div class="flex-grow-1 min-w-0">
                    <div class="text-body-1 font-weight-bold">{{ adv.title || 'Без названия' }}</div>
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
            <div v-if="!visible.length" class="pa-5 text-caption text-medium-emphasis">Авансов нет.</div>
        </v-card>

        <v-dialog
            :model-value="showTopUp"
            max-width="420"
            @update:model-value="(v) => { showTopUp = v; if (!v) resetTopUpForm(); }"
        >
            <v-card class="pa-5">
                <h3 class="text-h6 font-weight-bold mb-3">
                    {{ editingTopUpId ? 'Редактировать пополнение' : 'Пополнение кошелька' }}
                </h3>
                <v-text-field v-model="topUpForm.title" label="Название" class="mb-2" />
                <v-text-field v-model="topUpForm.amount" type="number" label="Сумма, ₽" min="0" class="mb-2" />
                <v-select
                    v-model="topUpForm.disbursement_method_id"
                    :items="methodItems"
                    item-title="label"
                    item-value="id"
                    label="Способ получения"
                    class="mb-2"
                />
                <v-text-field v-model="topUpForm.note" label="Комментарий" class="mb-3" />
                <div class="d-flex justify-end ga-2">
                    <v-btn variant="text" @click="showTopUp = false; resetTopUpForm()">Отмена</v-btn>
                    <v-btn color="primary" @click="submitTopUp">
                        {{ editingTopUpId ? 'Сохранить' : 'Пополнить' }}
                    </v-btn>
                </div>
            </v-card>
        </v-dialog>

        <v-dialog
            :model-value="showFreeExpense"
            max-width="480"
            @update:model-value="(v) => { showFreeExpense = v; if (!v) resetFreeExpenseForm(); }"
        >
            <v-card class="pa-5">
                <h3 class="text-h6 font-weight-bold mb-3">
                    {{ editingExpenseId ? 'Редактировать трату' : 'Трата со свободного остатка' }}
                </h3>
                <v-text-field v-model="freeExpense.amount" type="number" label="Сумма, ₽" min="0" class="mb-2" />
                <v-select
                    v-model="freeExpense.article_id"
                    :items="store.dictionaries.value.expenseArticles"
                    item-title="label"
                    item-value="id"
                    label="Статья"
                    class="mb-2"
                />
                <v-select
                    v-model="freeExpense.supplier_id"
                    :items="supplierItems"
                    item-title="title"
                    item-value="id"
                    label="Поставщик"
                    class="mb-2"
                />
                <v-text-field v-model="freeExpense.description" label="Название / описание" class="mb-3" />
                <div class="d-flex justify-space-between ga-2">
                    <v-btn
                        v-if="editingExpenseId"
                        variant="text"
                        color="error"
                        @click="store.removeExpense(editingExpenseId); showFreeExpense = false; resetFreeExpenseForm()"
                    >
                        Удалить
                    </v-btn>
                    <div class="d-flex justify-end ga-2 flex-grow-1">
                        <v-btn variant="text" @click="showFreeExpense = false; resetFreeExpenseForm()">Отмена</v-btn>
                        <v-btn color="primary" @click="submitFreeExpense">
                            {{ editingExpenseId ? 'Сохранить' : 'Списать' }}
                        </v-btn>
                    </div>
                </div>
            </v-card>
        </v-dialog>
    </AppLayout>
</template>
