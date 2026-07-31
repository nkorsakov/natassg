<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useDisplay, useTheme } from 'vuetify';
import TaskCreateDialog from '@/Components/TaskCreateDialog.vue';
import TaskDetailDialog from '@/Components/TaskDetailDialog.vue';
import EventDetailDialog from '@/Components/EventDetailDialog.vue';
import AdvanceDetailDialog from '@/Components/AdvanceDetailDialog.vue';
import AppearanceMenu from '@/Components/AppearanceMenu.vue';
import { useAppearance } from '@/composables/useAppearance';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';
import { useWorkspaceUi } from '@/composables/useWorkspaceUi';

const props = defineProps({
    title: { type: String, default: 'SkyDesk' },
    subtitle: { type: String, default: '' },
    showFab: { type: Boolean, default: true },
});

const { mdAndUp } = useDisplay();
const theme = useTheme();
const { isDark } = useAppearance();
const store = useSkyDeskStore();
const {
    taskOpen,
    taskId,
    eventOpen,
    eventId,
    advanceOpen,
    advanceId,
    quickCreateOpen,
    quickCreateEventId,
    openTask,
    openEvent,
    openAdvance,
    openQuickCreate,
} = useWorkspaceUi();
const page = usePage();
const drawer = ref(true);

watch(mdAndUp, (v) => {
    drawer.value = v;
}, { immediate: true });

const items = computed(() => [
    { title: 'Главная', icon: 'mdi-home-outline', href: '/dashboard', badge: null },
    { title: 'Поручения', icon: 'mdi-check-circle-outline', href: '/tasks', badge: store.activeTaskCount.value },
    { title: 'Календарь', icon: 'mdi-calendar-month-outline', href: '/calendar', badge: null },
    { title: 'Финансы', icon: 'mdi-currency-rub', href: '/finance', badge: store.pendingAdvanceCount.value || null },
]);

const currentPath = computed(() => page.url.split('?')[0]);
const crumb = computed(() => {
    if (currentPath.value === '/settings') return 'Настройки';
    const hit = items.value.find((i) => i.href === currentPath.value);
    return hit?.title ?? props.title;
});

const todayLabel = computed(() =>
    new Date().toLocaleDateString('ru-RU', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }),
);

const primaryColor = computed(() => theme.current.value.colors.primary);
const profile = computed(() => store.profile.value);

const isActive = (href) => currentPath.value === href;
const go = (href) => router.visit(href);

const openCreate = () => openQuickCreate();
const openSettings = () => router.visit('/settings');

const onTaskCreated = (id) => openTask(id);

const onEventCreateTask = (eventIdForLink) => {
    openQuickCreate(eventIdForLink);
};

defineExpose({ openCreate, openTask, openEvent, openAdvance });
</script>

<template>
    <v-app>
        <!-- Desktop sidebar -->
        <v-navigation-drawer
            v-if="mdAndUp"
            v-model="drawer"
            permanent
            width="268"
            :border="0"
            class="skydesk-drawer"
            :class="{ 'skydesk-drawer--dark': isDark }"
        >
            <div class="skydesk-drawer__glow" aria-hidden="true" />
            <div class="skydesk-drawer__grid" aria-hidden="true" />

            <div class="skydesk-drawer__brand px-5 pt-7 pb-6">
                <div class="d-flex align-center ga-3">
                    <div
                        class="skydesk-mark"
                        :style="{ background: primaryColor }"
                    >
                        ✦
                    </div>
                    <div>
                        <div class="skydesk-wordmark">SkyDesk</div>
                        <div class="skydesk-wordmark__sub">Рабочее пространство</div>
                    </div>
                </div>
            </div>

            <v-list nav density="comfortable" class="skydesk-drawer__nav px-3">
                <v-list-item
                    v-for="item in items"
                    :key="item.href"
                    :active="isActive(item.href)"
                    :prepend-icon="item.icon"
                    :title="item.title"
                    rounded="lg"
                    class="mb-1"
                    @click="go(item.href)"
                >
                    <template v-if="item.badge" #append>
                        <v-chip
                            size="x-small"
                            variant="flat"
                            class="skydesk-drawer__badge"
                        >
                            {{ item.badge }}
                        </v-chip>
                    </template>
                </v-list-item>
            </v-list>

            <template #append>
                <div class="skydesk-drawer__foot pa-3">
                    <v-btn
                        color="primary"
                        block
                        height="45"
                        prepend-icon="mdi-plus"
                        class="skydesk-drawer__cta"
                        @click="openCreate"
                    >
                        Новое поручение
                    </v-btn>
                    <div class="d-flex align-center ga-3 mt-5 pt-4 skydesk-drawer__user">
                        <v-avatar size="31" color="primary">
                            <span class="text-caption font-weight-bold">{{ profile.initials }}</span>
                        </v-avatar>
                        <div class="flex-grow-1 min-w-0">
                            <div class="text-body-2 font-weight-bold">{{ profile.name }}</div>
                            <div class="skydesk-drawer__role">{{ profile.role }}</div>
                        </div>
                        <v-btn
                            icon
                            variant="text"
                            size="small"
                            class="skydesk-drawer__settings"
                            aria-label="Настройки"
                            title="Настройки"
                            @click="openSettings"
                        >
                            <v-icon size="20">mdi-cog-outline</v-icon>
                        </v-btn>
                    </div>
                </div>
            </template>
        </v-navigation-drawer>

        <!-- Desktop top bar -->
        <v-app-bar v-if="mdAndUp" flat border color="background" density="comfortable">
            <div class="px-6 text-body-2 text-medium-emphasis w-100 d-flex align-center justify-space-between">
                <div class="d-flex align-center ga-2">
                    <span class="skydesk-wordmark skydesk-wordmark--bar">SkyDesk</span>
                    <span class="text-medium-emphasis">/</span>
                    <span class="font-weight-bold text-high-emphasis">{{ crumb }}</span>
                </div>
                <div class="d-flex align-center ga-2">
                    <span class="text-body-2 font-weight-medium text-capitalize">{{ todayLabel }}</span>
                    <AppearanceMenu />
                </div>
            </div>
        </v-app-bar>

        <!-- Mobile top bar -->
        <v-app-bar v-else flat color="background" density="comfortable" class="px-1">
            <div class="d-flex align-center ga-2 ps-2">
                <div
                    class="skydesk-mark skydesk-mark--sm"
                    :style="{ background: primaryColor }"
                >
                    ✦
                </div>
                <span class="skydesk-wordmark skydesk-wordmark--mobile">SkyDesk</span>
            </div>
            <template #append>
                <AppearanceMenu />
            </template>
        </v-app-bar>

        <v-main class="skydesk-main bg-background">
            <v-container :fluid="mdAndUp" :class="mdAndUp ? 'pa-8' : 'pa-4'" style="max-width:1560px">
                <div class="d-flex align-end justify-space-between mb-6 flex-wrap ga-3">
                    <div>
                        <h1 class="skydesk-page-title mb-1">
                            <slot name="heading">{{ title }}</slot>
                        </h1>
                        <p v-if="subtitle || $slots.subtitle" class="text-body-2 text-medium-emphasis mb-0">
                            <slot name="subtitle">{{ subtitle }}</slot>
                        </p>
                    </div>
                    <div v-if="mdAndUp" class="d-flex ga-2">
                        <slot name="actions">
                            <v-btn v-if="showFab" color="primary" prepend-icon="mdi-plus" @click="openCreate">
                                Новое поручение
                            </v-btn>
                        </slot>
                    </div>
                </div>

                <slot />
            </v-container>
        </v-main>

        <!-- Mobile bottom nav -->
        <v-bottom-navigation
            v-if="!mdAndUp"
            grow
            height="72"
            color="primary"
            :bg-color="isDark ? 'rgba(30,30,34,.94)' : 'rgba(255,255,255,.94)'"
            elevation="8"
            style="backdrop-filter:blur(18px)"
        >
            <v-btn
                v-for="item in items"
                :key="item.href"
                :value="item.href"
                :active="isActive(item.href)"
                @click="go(item.href)"
            >
                <v-badge v-if="item.badge" :content="item.badge" color="primary" floating>
                    <v-icon>{{ item.icon }}</v-icon>
                </v-badge>
                <v-icon v-else>{{ item.icon }}</v-icon>
                <span>{{ item.title }}</span>
            </v-btn>
        </v-bottom-navigation>

        <!-- Mobile FAB -->
        <v-btn
            v-if="!mdAndUp && showFab"
            color="primary"
            icon
            size="x-large"
            elevation="8"
            class="skydesk-fab"
            style="position:fixed;right:20px;bottom:88px;z-index:21;border-radius:18px !important"
            @click="openCreate"
        >
            <v-icon size="32">mdi-plus</v-icon>
        </v-btn>

        <TaskCreateDialog
            v-model="quickCreateOpen"
            :event-id="quickCreateEventId"
            @created="onTaskCreated"
        />
        <TaskDetailDialog
            v-model="taskOpen"
            :task-id="taskId"
            @open-task="openTask"
            @open-event="openEvent"
            @open-advance="openAdvance"
        />
        <EventDetailDialog
            v-model="eventOpen"
            :event-id="eventId"
            @open-task="openTask"
            @create-task="onEventCreateTask"
        />
        <AdvanceDetailDialog
            v-model="advanceOpen"
            :advance-id="advanceId"
            @open-task="openTask"
        />
    </v-app>
</template>

<style scoped>
.skydesk-drawer {
    background:
        radial-gradient(120% 80% at 10% -10%, rgba(var(--v-theme-primary), 0.38), transparent 52%),
        radial-gradient(90% 60% at 110% 30%, rgba(255, 173, 77, 0.14), transparent 46%),
        linear-gradient(165deg, #1a1730 0%, #2a2450 48%, #191827 100%) !important;
    color: #f4f2ff;
    overflow: hidden;
}

.skydesk-drawer--dark {
    background:
        radial-gradient(120% 80% at 10% -10%, rgba(var(--v-theme-primary), 0.28), transparent 52%),
        radial-gradient(90% 60% at 110% 30%, rgba(255, 173, 77, 0.1), transparent 46%),
        linear-gradient(165deg, #0e0e12 0%, #17161f 48%, #101014 100%) !important;
}

.skydesk-drawer__glow {
    position: absolute;
    width: 220px;
    height: 220px;
    right: -70px;
    bottom: 80px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(var(--v-theme-primary), 0.42), transparent 68%);
    filter: blur(10px);
    pointer-events: none;
    z-index: 0;
}

.skydesk-drawer__grid {
    position: absolute;
    inset: 0;
    opacity: 0.14;
    background-image:
        linear-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.08) 1px, transparent 1px);
    background-size: 28px 28px;
    mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.7), transparent 85%);
    pointer-events: none;
    z-index: 0;
}

.skydesk-drawer__brand,
.skydesk-drawer__nav,
.skydesk-drawer__foot {
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
    font-weight: 700;
    font-size: 16px;
    flex-shrink: 0;
    box-shadow: 0 8px 22px rgba(0, 0, 0, 0.22);
}

.skydesk-mark--sm {
    width: 28px;
    height: 28px;
    border-radius: 9px;
    font-size: 13px;
}

.skydesk-wordmark {
    font-family: Fraunces, Georgia, serif;
    font-weight: 700;
    font-size: 1.45rem;
    line-height: 1;
    letter-spacing: -0.04em;
    color: #f4f2ff;
}

.skydesk-wordmark--bar {
    font-size: 1.15rem;
    color: rgb(var(--v-theme-on-surface));
}

.skydesk-wordmark--mobile {
    font-size: 1.25rem;
    color: rgb(var(--v-theme-on-surface));
}

.skydesk-wordmark__sub {
    margin-top: 5px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgba(244, 242, 255, 0.55);
    font-family: Manrope, system-ui, sans-serif;
}

.skydesk-drawer__nav :deep(.v-list-item) {
    color: rgba(244, 242, 255, 0.78);
}

.skydesk-drawer__nav :deep(.v-list-item--active) {
    background: rgba(255, 255, 255, 0.12) !important;
    color: #fff !important;
}

.skydesk-drawer__nav :deep(.v-list-item:hover) {
    background: rgba(255, 255, 255, 0.08) !important;
}

.skydesk-drawer__nav :deep(.v-list-item__prepend .v-icon) {
    opacity: 0.9;
}

.skydesk-drawer__badge {
    background: rgba(255, 255, 255, 0.16) !important;
    color: #fff !important;
    font-weight: 700;
}

.skydesk-drawer__cta {
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.22);
}

.skydesk-drawer__user {
    border-top: 1px solid rgba(255, 255, 255, 0.12);
    color: #f4f2ff;
}

.skydesk-drawer__role {
    font-size: 12px;
    color: rgba(244, 242, 255, 0.55);
}

.skydesk-drawer__settings {
    color: rgba(244, 242, 255, 0.72) !important;
    flex-shrink: 0;
}

.skydesk-drawer__settings:hover {
    color: #fff !important;
    background: rgba(255, 255, 255, 0.1) !important;
}

.skydesk-page-title {
    font-family: Fraunces, Georgia, serif;
    font-size: clamp(1.65rem, 2.4vw, 2rem);
    font-weight: 700;
    letter-spacing: -0.03em;
    line-height: 1.15;
}
</style>
