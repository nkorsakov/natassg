<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useDisplay, useTheme } from 'vuetify';
import TaskCreateDialog from '@/Components/TaskCreateDialog.vue';
import AppearanceMenu from '@/Components/AppearanceMenu.vue';
import { useAppearance } from '@/composables/useAppearance';

const props = defineProps({
    title: { type: String, default: 'Natassg' },
    subtitle: { type: String, default: '' },
    showFab: { type: Boolean, default: true },
});

const emit = defineEmits(['create-task', 'create-advance']);

const { mdAndUp } = useDisplay();
const theme = useTheme();
const { isDark } = useAppearance();
const page = usePage();
const drawer = ref(true);
const taskDialog = ref(false);

watch(mdAndUp, (v) => {
    drawer.value = v;
}, { immediate: true });

const items = [
    { title: 'Главная', icon: 'mdi-home-outline', href: '/dashboard', badge: null },
    { title: 'Поручения', icon: 'mdi-check-circle-outline', href: '/tasks', badge: 8 },
    { title: 'Календарь', icon: 'mdi-calendar-month-outline', href: '/calendar', badge: null },
    { title: 'Финансы', icon: 'mdi-currency-rub', href: '/finance', badge: 3 },
];

const currentPath = computed(() => page.url.split('?')[0]);
const crumb = computed(() => {
    const hit = items.find((i) => i.href === currentPath.value);
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

const isActive = (href) => currentPath.value === href;
const go = (href) => router.visit(href);

const openCreate = () => {
    taskDialog.value = true;
};

defineExpose({ openCreate });
</script>

<template>
    <v-app>
        <!-- Desktop sidebar -->
        <v-navigation-drawer
            v-if="mdAndUp"
            v-model="drawer"
            permanent
            width="254"
            color="surface"
            border
        >
            <div class="d-flex align-center ga-3 px-4 pt-6 pb-8">
                <div
                    class="d-flex align-center justify-center text-white font-weight-bold"
                    style="width:30px;height:30px;border-radius:10px"
                    :style="{ background: primaryColor }"
                >
                    ✦
                </div>
                <span class="text-body-1 font-weight-bold">Личный помощник</span>
            </div>

            <div
                class="text-uppercase text-caption px-5 pb-2 text-medium-emphasis"
                style="letter-spacing:.08em;font-weight:700"
            >
                Рабочее пространство
            </div>

            <v-list nav density="comfortable" class="px-2">
                <v-list-item
                    v-for="item in items"
                    :key="item.href"
                    :active="isActive(item.href)"
                    :prepend-icon="item.icon"
                    :title="item.title"
                    rounded="lg"
                    class="mb-1"
                    color="primary"
                    @click="go(item.href)"
                >
                    <template v-if="item.badge" #append>
                        <v-chip size="x-small" color="primary" variant="tonal">{{ item.badge }}</v-chip>
                    </template>
                </v-list-item>
            </v-list>

            <template #append>
                <div class="pa-3">
                    <v-btn color="primary" block height="45" prepend-icon="mdi-plus" @click="openCreate">
                        Новое поручение
                    </v-btn>
                    <div class="d-flex align-center ga-3 mt-5 pt-4 natassg-divider-top">
                        <v-avatar size="31" color="primary" variant="tonal">
                            <span class="text-caption font-weight-bold">АМ</span>
                        </v-avatar>
                        <div>
                            <div class="text-body-2 font-weight-bold">Анна М.</div>
                            <div class="text-caption text-medium-emphasis">Личный помощник</div>
                        </div>
                    </div>
                </div>
            </template>
        </v-navigation-drawer>

        <!-- Desktop top bar -->
        <v-app-bar v-if="mdAndUp" flat border color="background" density="comfortable">
            <div class="px-6 text-body-2 text-medium-emphasis w-100 d-flex align-center justify-space-between">
                <div>
                    Рабочее пространство /
                    <span class="font-weight-bold text-high-emphasis">{{ crumb }}</span>
                </div>
                <div class="d-flex align-center ga-2">
                    <span class="text-body-2 font-weight-medium text-capitalize">{{ todayLabel }}</span>
                    <AppearanceMenu />
                    <v-btn icon variant="outlined" size="small" color="secondary">
                        <v-icon>mdi-magnify</v-icon>
                    </v-btn>
                    <v-btn icon variant="outlined" size="small" color="secondary">
                        <v-badge dot color="error" location="top end" offset-x="6" offset-y="6">
                            <v-icon>mdi-bell-outline</v-icon>
                        </v-badge>
                    </v-btn>
                </div>
            </div>
        </v-app-bar>

        <!-- Mobile top bar -->
        <v-app-bar v-else flat color="background" density="comfortable" class="px-1">
            <v-toolbar-title class="font-weight-bold text-body-1">Личный помощник</v-toolbar-title>
            <template #append>
                <AppearanceMenu />
                <v-btn icon variant="text">
                    <v-badge dot color="error">
                        <v-icon>mdi-bell-outline</v-icon>
                    </v-badge>
                </v-btn>
            </template>
        </v-app-bar>

        <v-main class="natassg-main bg-background">
            <v-container :fluid="mdAndUp" :class="mdAndUp ? 'pa-8' : 'pa-4'" style="max-width:1560px">
                <div class="d-flex align-end justify-space-between mb-6 flex-wrap ga-3">
                    <div>
                        <h1 class="text-h4 font-weight-bold mb-1" style="letter-spacing:-1px">
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
            class="natassg-fab"
            style="position:fixed;right:20px;bottom:88px;z-index:21;border-radius:18px !important"
            @click="openCreate"
        >
            <v-icon size="32">mdi-plus</v-icon>
        </v-btn>

        <TaskCreateDialog v-model="taskDialog" @created="emit('create-task', $event)" />
    </v-app>
</template>

<style scoped>
.natassg-divider-top {
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
