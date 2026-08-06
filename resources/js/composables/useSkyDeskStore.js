import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const visitOpts = {
    preserveScroll: true,
    preserveState: true,
    onError: (errors) => {
        const first = Object.values(errors || {}).find((v) => v != null);
        const msg = Array.isArray(first) ? first[0] : first;
        if (msg) window.alert(String(msg));
    },
};

function emptyWorkspace() {
    return {
        profile: { name: '', initials: '', role: '' },
        dictionaries: {
            statuses: [],
            priorities: [],
            taskTypes: [],
            eventTypes: [],
            advanceStatuses: [],
            expenseArticles: [],
            disbursementMethods: [],
        },
        tasks: [],
        events: [],
        advances: [],
        expenses: [],
        receipts: [],
        contacts: [],
        suppliers: [],
        wallet: {
            balance: 0,
            on_hand: 0,
            wallet: 0,
            free: 0,
            in_advances: 0,
            unassigned: 0,
            currency: 'RUB',
            transactions: [],
        },
    };
}

function dictById(list, id) {
    return list?.find((i) => i.id === id) ?? null;
}

let updateTimers = {};

function debouncedPut(key, url, data, delay = 900) {
    clearTimeout(updateTimers[key]);
    updateTimers[key] = setTimeout(() => {
        router.put(url, data, visitOpts);
        delete updateTimers[key];
    }, delay);
}

export function useSkyDeskStore() {
    const page = usePage();

    const skydesk = computed(() => page.props.skydesk || emptyWorkspace());

    const profile = computed(() => skydesk.value.profile);
    const dictionaries = computed(() => skydesk.value.dictionaries);
    const tasks = computed(() => skydesk.value.tasks || []);
    const events = computed(() => skydesk.value.events || []);
    const wallet = computed(() => skydesk.value.wallet || { balance: 0 });
    const advances = computed(() => skydesk.value.advances || []);
    const expenses = computed(() => skydesk.value.expenses || []);
    const receipts = computed(() => skydesk.value.receipts || []);
    const contacts = computed(() => skydesk.value.contacts || []);
    const suppliers = computed(() => skydesk.value.suppliers || []);

    const rootTasks = computed(() => tasks.value.filter((t) => !t.parent_id));

    const activeTaskCount = computed(
        () => tasks.value.filter((t) => !['done', 'cancelled', 'draft'].includes(t.status_id)).length,
    );

    const waitingMoneyCount = computed(
        () => tasks.value.filter((t) => t.status_id === 'waiting_money').length,
    );

    const pendingAdvanceCount = computed(
        () => advances.value.filter((a) => a.status_id === 'pending').length,
    );

    const upcomingEventCount = computed(() => {
        const d = new Date();
        d.setHours(12, 0, 0, 0);
        const today = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        return events.value.filter((e) => String(e.start || '').slice(0, 10) >= today).length;
    });

    const contactCount = computed(() => contacts.value.length);

    const financeAttentionCount = computed(
        () => advances.value.filter((a) => ['pending', 'received', 'reporting'].includes(a.status_id)).length,
    );

    const formatMoney = (n) => `${Number(n || 0).toLocaleString('ru-RU')} ₽`;

    const getStatus = (id) => dictById(dictionaries.value.statuses, id);
    const getPriority = (id) => dictById(dictionaries.value.priorities, id);
    const getTaskType = (id) => dictById(dictionaries.value.taskTypes, id);
    const getEventType = (id) => dictById(dictionaries.value.eventTypes, id);
    const getAdvanceStatus = (id) => dictById(dictionaries.value.advanceStatuses, id);
    const getExpenseArticle = (id) => dictById(dictionaries.value.expenseArticles, id);
    const getDisbursementMethod = (id) => dictById(dictionaries.value.disbursementMethods, id);

    const getTask = (id) => tasks.value.find((t) => String(t.id) === String(id)) ?? null;
    const getEvent = (id) => events.value.find((e) => String(e.id) === String(id)) ?? null;
    const getAdvance = (id) => advances.value.find((a) => String(a.id) === String(id)) ?? null;
    const getContact = (id) => contacts.value.find((c) => String(c.id) === String(id)) ?? null;
    const getSupplier = (id) => suppliers.value.find((s) => String(s.id) === String(id)) ?? null;

    const childrenOf = (parentId) =>
        tasks.value.filter((t) => String(t.parent_id) === String(parentId));

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
        return (ev.task_ids || []).map((tid) => getTask(tid)).filter(Boolean);
    };

    const eventsForTask = (taskId) => {
        const task = getTask(taskId);
        if (!task) return [];
        return (task.event_ids || []).map((eid) => getEvent(eid)).filter(Boolean);
    };

    const advancesForTask = (taskId) =>
        advances.value.filter((a) =>
            (a.task_ids || []).map(String).includes(String(taskId))
            || String(a.task_id) === String(taskId),
        );

    const expensesForAdvance = (advanceId) => {
        const adv = getAdvance(advanceId);
        if (adv?.expenses) return adv.expenses;
        return expenses.value.filter((x) => String(x.advance_id) === String(advanceId));
    };

    const receiptsForExpense = (expenseId) => {
        const fromList = expenses.value.find((e) => String(e.id) === String(expenseId));
        if (fromList?.receipts) return fromList.receipts;
        const fromAdv = advances.value
            .flatMap((a) => a.expenses || [])
            .find((e) => String(e.id) === String(expenseId));
        if (fromAdv?.receipts) return fromAdv.receipts;
        return receipts.value.filter((r) => String(r.expense_id) === String(expenseId));
    };

    const advanceSpent = (advanceId) => {
        const adv = getAdvance(advanceId);
        if (adv?.spent != null) return Number(adv.spent);
        return expensesForAdvance(advanceId).reduce((s, x) => s + Number(x.amount || 0), 0);
    };

    const advanceRemaining = (advanceId) => {
        const a = getAdvance(advanceId);
        if (!a) return 0;
        if (a.remaining != null) return Number(a.remaining);
        return Number(a.amount) - advanceSpent(advanceId);
    };

    const updateProfile = (patch) => {
        router.put('/settings/profile', {
            name: patch.name,
            initials: patch.initials,
            role: patch.role,
        }, visitOpts);
    };

    const updateDictItem = (key, id, patch) => {
        router.put(`/settings/dictionaries/${key}/${id}`, patch, visitOpts);
    };

    const addDictItem = (key, item) => {
        router.post(`/settings/dictionaries/${key}`, {
            label: item.label,
            color: item.color,
            icon: item.icon,
        }, visitOpts);
    };

    const removeDictItem = (key, id) => {
        const list = dictionaries.value[key];
        if (!list || list.length <= 1) return false;
        router.delete(`/settings/dictionaries/${key}/${id}`, {
            ...visitOpts,
            onError: (errors) => {
                const msg = errors?.dict;
                if (msg) window.alert(Array.isArray(msg) ? msg[0] : msg);
            },
        });
        return true;
    };

    const createTask = (payload = {}) =>
        new Promise((resolve) => {
            router.post('/tasks', {
                title: Object.prototype.hasOwnProperty.call(payload, 'title')
                    ? String(payload.title ?? '').trim()
                    : 'Новое поручение',
                parent_id: payload.parent_id ?? null,
                status_id: payload.status_id || 'new',
                priority_id: payload.priority_id || 'normal',
                type_id: payload.type_id || 'purchase',
                deadline: payload.deadline ?? null,
                note: payload.note || null,
                event_id: payload.event_id || null,
                event_ids: payload.event_ids || null,
            }, {
                ...visitOpts,
                onSuccess: (pageResult) => {
                    const id = pageResult.props.flash?.created_task_id;
                    resolve(id ? { id } : null);
                },
                onError: (errors) => {
                    visitOpts.onError?.(errors);
                    resolve(null);
                },
            });
        });

    const updateTask = (id, patch) => {
        debouncedPut(`task-${id}`, `/tasks/${id}`, patch);
    };

    const makeTaskRoot = (id) => {
        router.post(`/tasks/${id}/make-root`, {}, visitOpts);
    };

    const closeTaskCascade = (id) => {
        router.post(`/tasks/${id}/close`, {}, visitOpts);
    };

    const linkTaskEvent = (taskId, eventId) => {
        router.post(`/tasks/${taskId}/events`, { event_id: eventId }, visitOpts);
    };

    const unlinkTaskEvent = (taskId, eventId) => {
        router.delete(`/tasks/${taskId}/events/${eventId}`, visitOpts);
    };

    const createEvent = (payload = {}) =>
        new Promise((resolve) => {
            router.post('/events', {
                title: payload.title?.trim() || 'Событие',
                type_id: payload.type_id || 'other',
                start: payload.start || new Date().toISOString().slice(0, 16),
                end: payload.end ?? null,
                allDay: !!payload.allDay,
                place: payload.place || '',
                note: payload.note || '',
                task_ids: payload.task_ids || [],
            }, {
                ...visitOpts,
                onSuccess: (pageResult) => {
                    const id = pageResult.props.flash?.created_event_id;
                    resolve({ id });
                },
            });
        });

    const updateEvent = (id, patch) => {
        const data = {};
        ['title', 'type_id', 'start', 'end', 'allDay', 'place', 'note', 'task_ids'].forEach((k) => {
            if (Object.prototype.hasOwnProperty.call(patch, k)) data[k] = patch[k];
        });
        debouncedPut(`event-${id}`, `/events/${id}`, data);
    };

    const createAdvance = (payload = {}) =>
        new Promise((resolve) => {
            router.post('/advances', {
                title: Object.prototype.hasOwnProperty.call(payload, 'title')
                    ? String(payload.title ?? '').trim()
                    : '',
                task_id: payload.task_id ?? null,
                task_ids: payload.task_ids ?? null,
                amount: Number(payload.amount) || 0,
                status_id: payload.status_id || 'pending',
                disbursement_method_id: payload.disbursement_method_id || null,
                note: payload.note || '',
            }, {
                ...visitOpts,
                onSuccess: (pageResult) => {
                    const id = pageResult.props.flash?.created_advance_id;
                    resolve({ id });
                },
            });
        });

    const updateAdvance = (id, patch) => {
        debouncedPut(`advance-${id}`, `/advances/${id}`, patch);
    };

    const removeAdvance = (advanceId) => {
        router.delete(`/advances/${advanceId}`, visitOpts);
    };

    const removeTransaction = (transactionId) => {
        router.delete(`/wallet/transactions/${transactionId}`, {
            ...visitOpts,
            onError: (errors) => {
                const msg = errors?.transaction;
                if (msg) window.alert(Array.isArray(msg) ? msg[0] : msg);
            },
        });
    };

    const addExpense = (advanceId, payload = {}) => {
        const body = {
            amount: Number(payload.amount) || 0,
            description: payload.description || '',
            article_id: payload.article_id,
            supplier_id: payload.supplier_id || null,
            task_id: payload.task_id ?? null,
            debit_account: payload.debit_account || (advanceId ? 'advance' : 'unassigned'),
            occurred_at: payload.occurred_at || null,
        };
        const receipts = Array.isArray(payload.receipts) ? payload.receipts.filter(Boolean) : [];
        const opts = receipts.length ? { ...visitOpts, forceFormData: true } : visitOpts;
        if (receipts.length) {
            body.receipts = receipts;
        }
        if (advanceId) {
            router.post(`/advances/${advanceId}/expenses`, body, opts);
        } else {
            router.post('/expenses', {
                ...body,
                advance_id: payload.advance_id || null,
            }, opts);
        }
        return null;
    };

    const updateExpense = (expenseId, patch) => {
        debouncedPut(`expense-${expenseId}`, `/expenses/${expenseId}`, patch);
    };

    const removeExpense = (expenseId) => {
        if (!window.confirm('Удалить трату? Баланс будет скорректирован.')) return;
        router.delete(`/expenses/${expenseId}`, visitOpts);
    };

    const addReceipt = (expenseId, fileOrName) => {
        if (!(fileOrName instanceof File || fileOrName instanceof Blob)) return null;
        const advance = advances.value.find((a) =>
            (a.expenses || []).some((e) => String(e.id) === String(expenseId)),
        );
        const url = advance
            ? `/advances/${advance.id}/expenses/${expenseId}/receipts`
            : `/expenses/${expenseId}/receipts`;
        router.post(url, { file: fileOrName }, { ...visitOpts, forceFormData: true });
        return null;
    };

    const removeReceipt = (expenseId, receiptId) => {
        router.delete(`/expenses/${expenseId}/receipts/${receiptId}`, visitOpts);
    };

    const topUpWallet = (payload = {}) => {
        router.post('/wallet/topups', {
            amount: Number(payload.amount) || 0,
            note: payload.note || '',
            title: payload.title || '',
            disbursement_method_id: payload.disbursement_method_id || null,
            occurred_at: payload.occurred_at || null,
        }, visitOpts);
    };

    const updateTopUp = (transactionId, payload = {}) => {
        router.put(`/wallet/topups/${transactionId}`, {
            amount: Number(payload.amount) || 0,
            note: payload.note || '',
            title: payload.title || '',
            disbursement_method_id: payload.disbursement_method_id || null,
            occurred_at: payload.occurred_at || null,
        }, visitOpts);
    };

    const closeAdvanceToWallet = (advanceId) => {
        router.post(`/advances/${advanceId}/close-to-wallet`, {}, visitOpts);
    };

    const closeAdvanceWriteOff = (advanceId) => {
        router.post(`/advances/${advanceId}/close-writeoff`, {}, visitOpts);
    };

    const attachExpenseToAdvance = (advanceId, expenseId, debitAccount = 'advance') => {
        router.post(`/advances/${advanceId}/expenses/${expenseId}/attach`, {
            debit_account: debitAccount,
        }, visitOpts);
    };

    const detachExpenseFromAdvance = (advanceId, expenseId) => {
        router.post(`/advances/${advanceId}/expenses/${expenseId}/detach`, {}, visitOpts);
    };

    const createSupplier = (payload = {}) =>
        new Promise((resolve) => {
            router.post('/settings/suppliers', {
                name: String(payload.name ?? '').trim(),
                contact_id: payload.contact_id || null,
                note: payload.note || null,
            }, {
                ...visitOpts,
                onSuccess: (pageResult) => {
                    const id = pageResult.props.flash?.created_supplier_id;
                    resolve({ id });
                },
            });
        });

    const updateSupplier = (id, patch) => {
        debouncedPut(`supplier-${id}`, `/settings/suppliers/${id}`, patch);
    };

    const removeSupplier = (id) => {
        if (!window.confirm('Удалить поставщика?')) return;
        router.delete(`/settings/suppliers/${id}`, visitOpts);
    };

    const createContact = (payload = {}) =>
        new Promise((resolve) => {
            router.post('/contacts', {
                name: Object.prototype.hasOwnProperty.call(payload, 'name')
                    ? String(payload.name ?? '').trim()
                    : '',
                role: payload.role || '',
                phone: payload.phone || '',
                note: payload.note || '',
                is_supplier: !!payload.is_supplier,
            }, {
                ...visitOpts,
                onSuccess: (pageResult) => {
                    const id = pageResult.props.flash?.created_contact_id;
                    resolve({ id });
                },
            });
        });

    const updateContact = (id, patch) => {
        debouncedPut(`contact-${id}`, `/contacts/${id}`, patch);
    };

    const removeContact = (id) => {
        router.delete(`/contacts/${id}`, visitOpts);
    };

    const uploadTaskAttachment = (taskId, file, meta = {}) => {
        router.post(`/tasks/${taskId}/attachments`, {
            file,
            width: meta.width ?? null,
            height: meta.height ?? null,
        }, { ...visitOpts, forceFormData: true });
    };

    const removeTaskAttachment = (taskId, attachmentId) => {
        router.delete(`/tasks/${taskId}/attachments/${attachmentId}`, visitOpts);
    };

    const createTaskReminder = (taskId, payload = {}) => {
        router.post(`/tasks/${taskId}/reminders`, {
            remind_at: payload.remind_at,
            message: payload.message || null,
        }, visitOpts);
    };

    const removeTaskReminder = (taskId, reminderId) => {
        router.delete(`/tasks/${taskId}/reminders/${reminderId}`, visitOpts);
    };

    const createTaskComment = (taskId, body) => {
        const text = String(body ?? '').trim();
        if (!text) return;
        router.post(`/tasks/${taskId}/comments`, { body: text }, visitOpts);
    };

    const updateTaskComment = (taskId, commentId, body) => {
        const text = String(body ?? '').trim();
        if (!text) return;
        router.put(`/tasks/${taskId}/comments/${commentId}`, { body: text }, visitOpts);
    };

    const removeTaskComment = (taskId, commentId) => {
        if (!window.confirm('Удалить комментарий?')) return;
        router.delete(`/tasks/${taskId}/comments/${commentId}`, visitOpts);
    };

    const resetStore = () => {
        // Данные теперь в БД — демо-сброс не применяется.
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
        contacts,
        rootTasks,
        activeTaskCount,
        waitingMoneyCount,
        pendingAdvanceCount,
        upcomingEventCount,
        contactCount,
        financeAttentionCount,
        formatMoney,
        getStatus,
        getPriority,
        getTaskType,
        getEventType,
        getAdvanceStatus,
        getExpenseArticle,
        getDisbursementMethod,
        getTask,
        getEvent,
        getAdvance,
        getContact,
        getSupplier,
        suppliers,
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
        removeAdvance,
        removeTransaction,
        addExpense,
        updateExpense,
        removeExpense,
        addReceipt,
        removeReceipt,
        topUpWallet,
        updateTopUp,
        closeAdvanceToWallet,
        closeAdvanceWriteOff,
        attachExpenseToAdvance,
        detachExpenseFromAdvance,
        createSupplier,
        updateSupplier,
        removeSupplier,
        createContact,
        updateContact,
        removeContact,
        uploadTaskAttachment,
        removeTaskAttachment,
        createTaskReminder,
        removeTaskReminder,
        createTaskComment,
        updateTaskComment,
        removeTaskComment,
        resetStore,
    };
}
