<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useTheme } from 'vuetify';
import AppearanceMenu from '@/Components/AppearanceMenu.vue';
import ReportPreview from '@/Components/ReportPreview.vue';

const props = defineProps({
    report: { type: Object, required: true },
});

const theme = useTheme();
const primaryColor = computed(() => theme.current.value.colors.primary);
const payload = computed(() => props.report?.payload || {});

const formatPeriod = computed(() => {
    const from = props.report?.period_from;
    const to = props.report?.period_to;
    const opts = { day: 'numeric', month: 'long', year: 'numeric' };
    const a = from ? new Date(`${from}T12:00:00`).toLocaleDateString('ru-RU', opts) : '—';
    const b = to ? new Date(`${to}T12:00:00`).toLocaleDateString('ru-RU', opts) : '—';
    return `${a} — ${b}`;
});
</script>

<template>
    <v-app>
        <Head :title="`Отчёт · ${formatPeriod}`" />

        <v-app-bar flat color="background" density="comfortable" border>
            <div class="d-flex align-center ga-2 ps-4">
                <div
                    class="skydesk-mark skydesk-mark--sm"
                    :style="{ background: primaryColor }"
                >
                    ✦
                </div>
                <div>
                    <div class="skydesk-wordmark skydesk-wordmark--mobile">SkyDesk</div>
                    <div class="text-caption text-medium-emphasis" style="line-height:1.1">
                        Отчёт руководителю
                    </div>
                </div>
            </div>
            <template #append>
                <AppearanceMenu class="pe-2" />
            </template>
        </v-app-bar>

        <v-main class="bg-background">
            <v-container class="pa-4 pa-md-8" style="max-width:860px">
                <ReportPreview
                    :payload="payload"
                    :period-from="report.period_from"
                    :period-to="report.period_to"
                />
            </v-container>
        </v-main>
    </v-app>
</template>

<style scoped>
.skydesk-mark {
    width: 36px;
    height: 36px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    color: #fff;
    font-size: 16px;
    font-weight: 700;
}
.skydesk-mark--sm {
    width: 30px;
    height: 30px;
    border-radius: 10px;
    font-size: 13px;
}
.skydesk-wordmark {
    font-family: Fraunces, Georgia, serif;
    font-weight: 700;
    letter-spacing: -0.03em;
    line-height: 1.1;
}
.skydesk-wordmark--mobile {
    font-size: 1.05rem;
}
</style>
