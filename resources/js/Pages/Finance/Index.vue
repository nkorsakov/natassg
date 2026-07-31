<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';
import { useWorkspaceUi } from '@/composables/useWorkspaceUi';

const store = useSkyDeskStore();
const { openAdvance, openTask } = useWorkspaceUi();

const filter = ref('all');

const filters = [
    { value: 'all', label: 'Все' },
    { value: 'pending', label: 'На согласовании' },
    { value: 'issued', label: 'Выдано' },
    { value: 'reporting', label: 'На отчёте' },
    { value: 'closed', label: 'Закрыто' },
];

const visible = computed(() => {
    if (filter.value === 'all') return store.advances.value;
    if (filter.value === 'issued') {
        return store.advances.value.filter((a) => ['issued', 'approved'].includes(a.status_id));
    }
    return store.advances.value.filter((a) => a.status_id === filter.value);
});

const summary = computed(() => {
    const pending = store.advances.value
        .filter((a) => a.status_id === 'pending')
        .reduce((s, a) => s + a.amount, 0);
    const issued = store.advances.value
        .filter((a) => ['issued', 'reporting', 'approved'].includes(a.status_id))
        .reduce((s, a) => s + a.amount, 0);
    const closed = store.advances.value
        .filter((a) => a.status_id === 'closed')
        .reduce((s, a) => s + a.amount, 0);
    return [
        { label: 'Ожидает одобрения', amount: store.formatMoney(pending), tone: 'orange' },
        { label: 'В обороте', amount: store.formatMoney(issued), tone: null },
        { label: 'Закрыто', amount: store.formatMoney(closed), tone: 'green' },
    ];
});

const createAdvance = async () => {
    const adv = await store.createAdvance({
        title: 'Новая заявка на аванс',
        amount: 10000,
        status_id: 'pending',
    });
    if (adv?.id) openAdvance(adv.id);
};
</script>

<template>
    <AppLayout
        title="Финансы"
        subtitle="Общий кошелёк, авансы и отчёт по тратам."
        :show-fab="false"
    >
        <template #actions>
            <v-btn color="primary" prepend-icon="mdi-cash-plus" @click="createAdvance">
                Заявка на аванс
            </v-btn>
        </template>

        <v-card class="pa-5 mb-5 skydesk-accent-panel">
            <div class="text-caption font-weight-bold text-primary mb-1">Общий кошелёк · на руках</div>
            <div class="text-h4 font-weight-bold" style="font-family:Fraunces,Georgia,serif;letter-spacing:-.03em">
                {{ store.formatMoney(store.wallet.value.balance) }}
            </div>
            <div class="text-caption text-medium-emphasis mt-1">
                Выданные авансы минус возвраты / обнуления / перерасход
            </div>
        </v-card>

        <div class="d-flex flex-wrap ga-3 mb-5">
            <v-card
                v-for="s in summary"
                :key="s.label"
                class="pa-4"
                style="flex:1 1 160px;min-width:160px"
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
                    <div class="text-body-1 font-weight-bold">{{ adv.title }}</div>
                    <div class="text-caption text-medium-emphasis">
                        {{ adv.note || 'Без описания' }}
                        <template v-if="adv.task_id">
                            ·
                            <a
                                class="text-primary"
                                style="text-decoration:none;font-weight:700"
                                @click.stop="openTask(adv.task_id)"
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
                        :style="{ color: store.getAdvanceStatus(adv.status_id)?.color }"
                    >
                        {{ store.getAdvanceStatus(adv.status_id)?.label }}
                    </v-chip>
                </div>
            </div>
        </v-card>
    </AppLayout>
</template>
