<script setup>
import { ref } from 'vue';
import { useDisplay } from 'vuetify';
import AppLayout from '@/Layouts/AppLayout.vue';
import TaskRow from '@/Components/TaskRow.vue';

defineProps({
    filters: { type: Array, default: () => [] },
    tasks: { type: Array, default: () => [] },
    weekProgress: { type: Object, default: () => ({ done: 17, total: 25, percent: 68 }) },
    insights: { type: Array, default: () => [] },
});

const { mdAndUp } = useDisplay();
const selectedFilter = ref('all');
</script>

<template>
    <AppLayout
        title="Поручения"
        subtitle="Все задачи, которые помогают держать день под контролем."
    >
        <div class="d-flex ga-2 mb-5" style="overflow-x:auto;padding-bottom:4px">
            <v-chip
                v-for="filter in filters"
                :key="filter.value"
                :variant="selectedFilter === filter.value ? 'tonal' : 'outlined'"
                :color="selectedFilter === filter.value ? 'primary' : undefined"
                class="flex-shrink-0"
                @click="selectedFilter = filter.value"
            >
                {{ filter.label }}
            </v-chip>
        </div>

        <v-row>
            <v-col cols="12" :md="insights.length ? 8 : 12">
                <v-card>
                    <div class="px-2 py-2">
                        <TaskRow
                            v-for="task in tasks"
                            :key="task.title"
                            v-bind="task"
                            show-side-meta
                            style="border-bottom:1px solid #f0f0f2;border-radius:0"
                        />
                    </div>
                </v-card>
            </v-col>

            <v-col v-if="mdAndUp && insights.length" cols="12" md="4">
                <v-card class="pa-5">
                    <h2 class="text-subtitle-1 font-weight-bold mb-3">Прогресс недели</h2>
                    <v-progress-linear
                        :model-value="weekProgress.percent"
                        color="primary"
                        height="7"
                        rounded
                    />
                    <div class="d-flex justify-space-between text-caption text-medium-emphasis mt-2">
                        <span>{{ weekProgress.done }} из {{ weekProgress.total }} поручений</span>
                        <b>{{ weekProgress.percent }}%</b>
                    </div>

                    <v-card
                        v-for="box in insights"
                        :key="box.title"
                        class="mt-4 pa-4 skydesk-accent-panel"
                    >
                        <div class="text-body-2 font-weight-bold mb-1">{{ box.title }}</div>
                        <div class="text-caption text-medium-emphasis" style="line-height:1.45">{{ box.text }}</div>
                    </v-card>
                </v-card>
            </v-col>
        </v-row>
    </AppLayout>
</template>
