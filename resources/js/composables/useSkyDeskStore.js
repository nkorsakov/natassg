import { computed, reactive } from 'vue';
import { createSeedState } from '@/mocks/seed';

const STORAGE_KEY = 'skydesk-store-v1';

function loadState() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (raw) {
            const data = JSON.parse(raw);
            if (data?.profile?.name === 'Анна М.' || data?.profile?.initials === 'АМ') {
                data.profile = {
                    ...data.profile,
                    name: 'Наталия Я.',
                    initials: 'НЯ',
                };
                localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
            }
            return data;
        }
    } catch {
        // ignore
    }
    return createSeedState();
}

const state = reactive(loadState());

function persist() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
}

function nextId(kind) {
    state.seq[kind] = (state.seq[kind] || 1) + 1;
    const prefix = { task: 't', event: 'e', advance: 'a', expense: 'x', receipt: 'r' }[kind];
    return `${prefix}${state.seq[kind]}`;
}

function dictById(list, id) {
    return list.find((i) => i.id === id) ?? null;
}

export function useSkyDeskStore() {
    const profile = computed(() => state.profile);
    const dictionaries = computed(() => state.dictionaries);
    const tasks = computed(() => state.tasks);
    const events = computed(() => state.events);
    const wallet = computed(() => state.wallet);
    const advances = computed(() => state.advances);
    const expenses = computed(() => state.expenses);
    const receipts = computed(() => state.receipts);

    const rootTasks = computed(() => state.tasks.filter((t) => !t.parent_id));

    const activeTaskCount = computed(
        () => state.tasks.filter((t) => !['done', 'cancelled', 'draft'].includes(t.status_id)).length,
    );

    const waitingMoneyCount = computed(
        () => state.tasks.filter((t) => t.status_id === 'waiting_money').length,
    );

    const pendingAdvanceCount = computed(
        () => state.advances.filter((a) => a.status_id === 'pending').length,
    );

    const formatMoney = (n) =>
        `${Number(n || 0).toLocaleString('ru-RU')} ₽`;

    const getStatus = (id) => dictById(state.dictionaries.statuses, id);
    const getPriority = (id) => dictById(state.dictionaries.priorities, id);
    const getTaskType = (id) => dictById(state.dictionaries.taskTypes, id);
    const getEventType = (id) => dictById(state.dictionaries.eventTypes, id);
    const getAdvanceStatus = (id) => dictById(state.dictionaries.advanceStatuses, id);

    const getTask = (id) => state.tasks.find((t) => t.id === id) ?? null;
    const getEvent = (id) => state.events.find((e) => e.id === id) ?? null;
    const getAdvance = (id) => state.advances.find((a) => a.id === id) ?? null;

    const childrenOf = (parentId) => state.tasks.filter((t) => t.parent_id === parentId);

    const descendantsOf = (id) => {
        const out = [];
        const walk = (pid) => {
            childrenOf(pid).forEach((c) => {
                out.push(c);
                walk(c.id);
            });
        };
        walk(id);
        return out;
    };

    const tasksForEvent = (eventId) => {
        const ev = getEvent(eventId);
        if (!ev) return [];
        return ev.task_ids.map((id) => getTask(id)).filter(Boolean);
    };

    const eventsForTask = (taskId) => {
        const task = getTask(taskId);
        if (!task) return [];
        return task.event_ids.map((id) => getEvent(id)).filter(Boolean);
    };

    const advancesForTask = (taskId) =>
        state.advances.filter((a) => a.task_id === taskId);

    const expensesForAdvance = (advanceId) =>
        state.expenses.filter((x) => x.advance_id === advanceId);

    const receiptsForExpense = (expenseId) =>
        state.receipts.filter((r) => r.expense_id === expenseId);

    const advanceSpent = (advanceId) =>
        expensesForAdvance(advanceId).reduce((s, x) => s + Number(x.amount || 0), 0);

    const advanceRemaining = (advanceId) => {
        const a = getAdvance(advanceId);
        if (!a) return 0;
        return Number(a.amount) - advanceSpent(advanceId);
    };

    // --- Profile / dictionaries ---
    const updateProfile = (patch) => {
        Object.assign(state.profile, patch);
        persist();
    };

    const updateDictItem = (key, id, patch) => {
        const list = state.dictionaries[key];
        const item = list?.find((i) => i.id === id);
        if (!item) return;
        Object.assign(item, patch);
        persist();
    };

    const addDictItem = (key, item) => {
        if (!state.dictionaries[key]) return;
        state.dictionaries[key].push(item);
        persist();
    };

    const removeDictItem = (key, id) => {
        const list = state.dictionaries[key];
        if (!list || list.length <= 1) return false;
        const idx = list.findIndex((i) => i.id === id);
        if (idx < 0) return false;
        list.splice(idx, 1);
        persist();
        return true;
    };

    // --- Tasks ---
    const createTask = (payload = {}) => {
        const id = nextId('task');
        const task = {
            id,
            title: payload.title?.trim() || 'Новое поручение',
            parent_id: payload.parent_id ?? null,
            status_id: payload.status_id || 'new',
            priority_id: payload.priority_id || 'normal',
            type_id: payload.type_id || 'purchase',
            deadline: payload.deadline ?? null,
            note: payload.note || '',
            event_ids: payload.event_ids ? [...payload.event_ids] : [],
            advance_ids: [],
        };
        state.tasks.push(task);
        (payload.event_ids || []).forEach((eid) => {
            const event = getEvent(eid);
            if (event && !event.task_ids.includes(id)) event.task_ids.push(id);
        });
        if (payload.event_id) {
            const event = getEvent(payload.event_id);
            if (event && !event.task_ids.includes(id)) event.task_ids.push(id);
            if (!task.event_ids.includes(payload.event_id)) task.event_ids.push(payload.event_id);
        }
        persist();
        return task;
    };

    const updateTask = (id, patch) => {
        const task = getTask(id);
        if (!task) return;
        Object.assign(task, patch);
        persist();
    };

    const makeTaskRoot = (id) => {
        const task = getTask(id);
        if (!task || !task.parent_id) return;
        task.parent_id = null;
        persist();
    };

    const closeTaskCascade = (id) => {
        const task = getTask(id);
        if (!task) return;
        task.status_id = 'done';
        descendantsOf(id).forEach((d) => {
            d.status_id = 'done';
        });
        persist();
    };

    const linkTaskEvent = (taskId, eventId) => {
        const task = getTask(taskId);
        const event = getEvent(eventId);
        if (!task || !event) return;
        if (!task.event_ids.includes(eventId)) task.event_ids.push(eventId);
        if (!event.task_ids.includes(taskId)) event.task_ids.push(taskId);
        persist();
    };

    const unlinkTaskEvent = (taskId, eventId) => {
        const task = getTask(taskId);
        const event = getEvent(eventId);
        if (task) task.event_ids = task.event_ids.filter((i) => i !== eventId);
        if (event) event.task_ids = event.task_ids.filter((i) => i !== taskId);
        persist();
    };

    // --- Events ---
    const createEvent = (payload = {}) => {
        const id = nextId('event');
        const event = {
            id,
            title: payload.title?.trim() || 'Событие',
            type_id: payload.type_id || 'other',
            start: payload.start || new Date().toISOString().slice(0, 16),
            end: payload.end ?? null,
            allDay: !!payload.allDay,
            place: payload.place || '',
            note: payload.note || '',
            task_ids: payload.task_ids ? [...payload.task_ids] : [],
        };
        state.events.push(event);
        (event.task_ids || []).forEach((tid) => {
            const t = getTask(tid);
            if (t && !t.event_ids.includes(id)) t.event_ids.push(id);
        });
        persist();
        return event;
    };

    const updateEvent = (id, patch) => {
        const event = getEvent(id);
        if (!event) return;
        Object.assign(event, patch);
        persist();
    };

    // --- Advances / wallet ---
    const createAdvance = (payload = {}) => {
        const id = nextId('advance');
        const advance = {
            id,
            title: payload.title?.trim() || 'Заявка на аванс',
            task_id: payload.task_id ?? null,
            amount: Number(payload.amount) || 0,
            status_id: payload.status_id || 'pending',
            note: payload.note || '',
            created_at: new Date().toISOString(),
            expense_ids: [],
        };
        state.advances.unshift(advance);
        if (advance.task_id) {
            const t = getTask(advance.task_id);
            if (t && !t.advance_ids.includes(id)) t.advance_ids.push(id);
        }
        persist();
        return advance;
    };

    const updateAdvance = (id, patch) => {
        const advance = getAdvance(id);
        if (!advance) return;
        const prev = advance.status_id;
        Object.assign(advance, patch);
        if (prev !== 'issued' && advance.status_id === 'issued') {
            state.wallet.balance += Number(advance.amount);
        }
        persist();
    };

    const addExpense = (advanceId, payload = {}) => {
        const advance = getAdvance(advanceId);
        if (!advance) return null;
        const id = nextId('expense');
        const expense = {
            id,
            advance_id: advanceId,
            amount: Number(payload.amount) || 0,
            description: payload.description || '',
            receipt_ids: [],
        };
        state.expenses.push(expense);
        advance.expense_ids.push(id);
        if (advance.status_id === 'issued') advance.status_id = 'reporting';
        persist();
        return expense;
    };

    const addReceipt = (expenseId, name = 'чек.jpg') => {
        const expense = state.expenses.find((x) => x.id === expenseId);
        if (!expense) return null;
        const id = nextId('receipt');
        const receipt = { id, expense_id: expenseId, name, placeholder: true };
        state.receipts.push(receipt);
        expense.receipt_ids.push(id);
        persist();
        return receipt;
    };

    const returnRemainderToWallet = (advanceId) => {
        const rem = advanceRemaining(advanceId);
        if (rem <= 0) return;
        state.wallet.balance += rem;
        const advance = getAdvance(advanceId);
        if (advance) advance.status_id = 'closed';
        persist();
    };

    const zeroAsUnknown = (advanceId) => {
        const rem = advanceRemaining(advanceId);
        if (rem <= 0) return;
        // "обнулить как неизвестное" — остаток списываем с кошелька без возврата
        state.wallet.balance -= rem;
        const advance = getAdvance(advanceId);
        if (advance) advance.status_id = 'closed';
        persist();
    };

    const recordOverspend = (advanceId) => {
        const rem = advanceRemaining(advanceId);
        if (rem >= 0) return;
        // перерасход уже уменьшил "на руках" через выдачу; фиксируем закрытие
        const advance = getAdvance(advanceId);
        if (advance) advance.status_id = 'closed';
        // overspend already means spent > amount; wallet was credited on issue, expenses don't auto-debit
        // Debit the overspend amount from wallet to reflect reality
        state.wallet.balance += rem; // rem is negative
        persist();
    };

    const closeAdvanceWithSettlement = (advanceId) => {
        const rem = advanceRemaining(advanceId);
        if (rem > 0) returnRemainderToWallet(advanceId);
        else if (rem < 0) recordOverspend(advanceId);
        else {
            const advance = getAdvance(advanceId);
            if (advance) advance.status_id = 'closed';
            persist();
        }
    };

    const resetStore = () => {
        const fresh = createSeedState();
        Object.keys(fresh).forEach((k) => {
            state[k] = fresh[k];
        });
        persist();
    };

    return {
        profile,
        dictionaries,
        tasks,
        events,
        wallet,
        advances,
        expenses,
        receipts,
        rootTasks,
        activeTaskCount,
        waitingMoneyCount,
        pendingAdvanceCount,
        formatMoney,
        getStatus,
        getPriority,
        getTaskType,
        getEventType,
        getAdvanceStatus,
        getTask,
        getEvent,
        getAdvance,
        childrenOf,
        descendantsOf,
        tasksForEvent,
        eventsForTask,
        advancesForTask,
        expensesForAdvance,
        receiptsForExpense,
        advanceSpent,
        advanceRemaining,
        updateProfile,
        updateDictItem,
        addDictItem,
        removeDictItem,
        createTask,
        updateTask,
        makeTaskRoot,
        closeTaskCascade,
        linkTaskEvent,
        unlinkTaskEvent,
        createEvent,
        updateEvent,
        createAdvance,
        updateAdvance,
        addExpense,
        addReceipt,
        returnRemainderToWallet,
        zeroAsUnknown,
        recordOverspend,
        closeAdvanceWithSettlement,
        resetStore,
        persist,
    };
}
