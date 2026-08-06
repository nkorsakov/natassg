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
const isDark = computed(() => !!theme.current.value.dark);

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

        <div class="report-public" :class="{ 'report-public--dark': isDark }">
            <div class="report-public__glow report-public__glow--a" aria-hidden="true" />
            <div class="report-public__glow report-public__glow--b" aria-hidden="true" />
            <div class="report-public__glow report-public__glow--c" aria-hidden="true" />

            <v-app-bar flat color="background" density="comfortable" border class="report-public__bar">
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

            <v-main>
                <v-container class="pa-4 pa-md-8" style="max-width:860px">
                    <ReportPreview
                        :payload="payload"
                        :period-from="report.period_from"
                        :period-to="report.period_to"
                    />
                </v-container>
            </v-main>
        </div>
    </v-app>
</template>

<style scoped>
.report-public {
    position: relative;
    min-height: 100vh;
    min-height: 100dvh;
    background: rgb(var(--v-theme-background));
}

.report-public__glow {
    position: fixed;
    border-radius: 50%;
    filter: blur(48px);
    pointer-events: none;
    z-index: 0;
}

.report-public__glow--a {
    width: 420px;
    height: 420px;
    top: -120px;
    left: -80px;
    background: rgba(105, 87, 238, 0.22);
}

.report-public__glow--b {
    width: 360px;
    height: 360px;
    top: 80px;
    right: -100px;
    background: rgba(255, 173, 77, 0.16);
}

.report-public__glow--c {
    width: 300px;
    height: 300px;
    bottom: 10%;
    left: 30%;
    background: rgba(55, 168, 120, 0.12);
}

.report-public--dark .report-public__glow--a {
    background: rgba(105, 87, 238, 0.28);
}

.report-public--dark .report-public__glow--b {
    background: rgba(255, 173, 77, 0.14);
}

.report-public--dark .report-public__glow--c {
    background: rgba(55, 168, 120, 0.1);
}

.report-public__bar,
.report-public :deep(.v-main) {
    position: relative;
    z-index: 1;
}

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
