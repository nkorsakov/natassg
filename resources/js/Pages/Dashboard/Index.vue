<script setup>
import { router } from '@inertiajs/vue3';
import { useDisplay } from 'vuetify';
import AppLayout from '@/Layouts/AppLayout.vue';
import TaskRow from '@/Components/TaskRow.vue';

defineProps({
    stats: { type: Array, default: () => [] },
    priorityTasks: { type: Array, default: () => [] },
    agenda: { type: Array, default: () => [] },
    financePreview: { type: Object, default: null },
});

const { mdAndUp } = useDisplay();
</script>

<template>
    <AppLayout
        title="Доброе утро, Анна"
        subtitle="Вот что требует вашего внимания сегодня."
    >
        <!-- Stats -->
        <div
            class="mb-6"
            :class="mdAndUp ? 'd-flex flex-wrap' : 'd-flex'"
            :style="mdAndUp
                ? 'gap:15px'
                : 'gap:10px;overflow-x:auto;margin:0 -16px;padding:0 16px 5px'"
        >
            <v-card
                v-for="stat in stats"
                :key="stat.label"
                class="pa-4"
                :style="mdAndUp
                    ? 'flex:1 1 0;min-width:180px;min-height:104px'
                    : 'flex:0 0 145px;min-height:111px'"
            >
                <div class="d-flex align-center ga-3 mb-3">
                    <div class="skydesk-stat-icon" :style="{ background: stat.bg }">
                        <v-icon :icon="stat.icon" size="18" :color="stat.color" />
                    </div>
                    <div class="text-h5 font-weight-bold" style="letter-spacing:-.5px">{{ stat.value }}</div>
                </div>
                <div class="text-caption text-medium-emphasis">{{ stat.label }}</div>
            </v-card>
        </div>

        <v-row>
            <v-col cols="12" :md="financePreview ? 8 : 12">
                <v-card>
                    <div class="d-flex align-center justify-space-between px-5 pt-5 pb-2">
                        <h2 class="text-subtitle-1 font-weight-bold mb-0">Приоритетные поручения</h2>
                        <v-btn variant="text" color="primary" size="small" @click="router.visit('/tasks')">
                            Все поручения →
                        </v-btn>
                    </div>
                    <div class="px-2 pb-3">
                        <TaskRow
                            v-for="task in priorityTasks"
                            :key="task.title"
                            v-bind="task"
                        />
                    </div>
                </v-card>
            </v-col>

            <v-col cols="12" md="4">
                <v-card class="mb-4">
                    <div class="d-flex align-center justify-space-between px-5 pt-5 pb-2">
                        <h2 class="text-subtitle-1 font-weight-bold mb-0">Сегодня</h2>
                        <v-btn variant="text" color="primary" size="small" @click="router.visit('/calendar')">
                            Календарь →
                        </v-btn>
                    </div>
                    <div class="px-5 pb-4">
                        <div
                            v-for="item in agenda"
                            :key="item.title"
                            class="d-flex ga-3 py-3 skydesk-row-divider"
                        >
                            <div class="text-caption text-medium-emphasis" style="width:43px">{{ item.time }}</div>
                            <div>
                                <div class="text-body-2 font-weight-bold d-flex align-center ga-2">
                                    <span
                                        style="width:7px;height:7px;border-radius:50%;display:inline-block"
                                        :style="{ background: item.dot }"
                                    />
                                    {{ item.title }}
                                </div>
                                <div class="text-caption text-medium-emphasis mt-1">{{ item.desc }}</div>
                            </div>
                        </div>
                    </div>
                </v-card>

                <v-card v-if="financePreview">
                    <div class="d-flex align-center justify-space-between px-5 pt-5 pb-2">
                        <h2 class="text-subtitle-1 font-weight-bold mb-0">Финансы</h2>
                        <v-btn variant="text" color="primary" size="small" @click="router.visit('/finance')">
                            Открыть →
                        </v-btn>
                    </div>
                    <div class="mx-5 mb-5 pa-4 skydesk-accent-panel">
                        <div class="d-flex justify-space-between text-caption font-weight-bold text-primary">
                            <span>{{ financePreview.label }}</span>
                            <span>{{ financePreview.count }}</span>
                        </div>
                        <div class="text-h5 font-weight-bold my-2">{{ financePreview.amount }}</div>
                        <div class="text-caption text-medium-emphasis">{{ financePreview.hint }}</div>
                    </div>
                </v-card>
            </v-col>
        </v-row>
    </AppLayout>
</template>
