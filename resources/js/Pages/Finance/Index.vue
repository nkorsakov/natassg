<script setup>
import { ref } from 'vue';
import { useDisplay } from 'vuetify';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    summary: { type: Array, default: () => [] },
    advances: { type: Array, default: () => [] },
});

const { mdAndUp } = useDisplay();
const tab = ref('active');

const statusColor = (status) => {
    if (status === 'pending') return 'warning';
    if (status === 'approved') return 'primary';
    if (status === 'ready') return 'success';
    return 'secondary';
};
</script>

<template>
    <AppLayout
        title="Финансы"
        subtitle="Авансовые заявки, выдача средств и отчетные расходы."
        :show-fab="false"
    >
        <template #actions>
            <v-btn color="primary" prepend-icon="mdi-plus">Авансовая заявка</v-btn>
        </template>

        <div
            class="mb-5"
            :class="mdAndUp ? 'd-flex' : 'd-flex'"
            :style="mdAndUp
                ? 'gap:14px'
                : 'gap:10px;overflow-x:auto;margin:0 -16px;padding:0 16px 5px'"
        >
            <v-card
                v-for="card in summary"
                :key="card.label"
                class="pa-4"
                :style="mdAndUp ? 'flex:1' : 'flex:0 0 153px'"
            >
                <div class="text-caption text-medium-emphasis">{{ card.label }}</div>
                <div
                    class="text-h5 font-weight-bold mt-2"
                    :class="{
                        'text-warning': card.tone === 'orange',
                        'text-success': card.tone === 'green',
                    }"
                >
                    {{ card.amount }}
                </div>
            </v-card>
        </div>

        <v-card>
            <div class="d-flex align-center justify-space-between px-5 pt-5 pb-2">
                <h2 class="text-subtitle-1 font-weight-bold mb-0">Авансовые заявки</h2>
                <v-btn variant="text" color="primary" size="small">Фильтры ▾</v-btn>
            </div>

            <v-tabs v-model="tab" color="primary" class="px-4">
                <v-tab value="active">Активные · 4</v-tab>
                <v-tab value="closed">Закрытые</v-tab>
                <v-tab value="all">Все заявки</v-tab>
            </v-tabs>

            <v-divider />

            <div class="px-4 py-2">
                <div
                    v-for="item in advances"
                    :key="item.title"
                    class="d-flex align-center ga-3 py-4"
                    style="border-bottom:1px solid #f0f0f2"
                >
                    <div class="flex-grow-1" style="min-width:0">
                        <div class="text-body-2 font-weight-bold">{{ item.title }}</div>
                        <div class="text-caption text-medium-emphasis text-truncate mt-1">{{ item.desc }}</div>
                    </div>
                    <div class="text-body-2 font-weight-bold text-no-wrap">{{ item.amount }}</div>
                    <v-chip
                        v-if="mdAndUp"
                        size="small"
                        class="natassg-pill"
                        :color="statusColor(item.status)"
                        variant="tonal"
                    >
                        {{ item.statusLabel }}
                    </v-chip>
                    <div v-if="mdAndUp" class="text-caption text-medium-emphasis text-no-wrap" style="width:100px">
                        {{ item.date }}
                    </div>
                    <v-btn icon variant="text" size="small" color="secondary">
                        <v-icon>mdi-dots-horizontal</v-icon>
                    </v-btn>
                </div>
            </div>
        </v-card>
    </AppLayout>
</template>
