<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useDisplay, useTheme } from 'vuetify';
import AppearanceMenu from '@/Components/AppearanceMenu.vue';
import { useAppearance } from '@/composables/useAppearance';

defineProps({
    status: { type: String, default: null },
});

const { mdAndUp } = useDisplay();
const theme = useTheme();
const { isDark } = useAppearance();

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

const showPassword = ref(false);
const telegramLoading = ref(false);
const telegramError = ref('');

const primaryColor = computed(() => theme.current.value.colors.primary);
const canSubmit = computed(
    () => form.email.trim().length > 0 && form.password.length > 0 && !form.processing,
);
const error = computed(
    () =>
        telegramError.value
        || form.errors.email
        || form.errors.password
        || form.errors.init_data
        || '',
);

const submit = () => {
    telegramError.value = '';
    if (!canSubmit.value) {
        return;
    }

    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};

const tryTelegramLogin = () => {
    const initData = window.Telegram?.WebApp?.initData;
    if (!initData) {
        return;
    }

    telegramLoading.value = true;
    telegramError.value = '';

    window.Telegram.WebApp.ready();
    window.Telegram.WebApp.expand?.();

    router.post('/auth/telegram', { init_data: initData }, {
        onError: (errors) => {
            telegramError.value = errors.init_data || 'Не удалось войти через Telegram.';
        },
        onFinish: () => {
            telegramLoading.value = false;
        },
    });
};

onMounted(() => {
    tryTelegramLogin();
});
</script>

<template>
    <Head title="Вход" />

    <v-app class="login-app">
        <div class="login-shell" :class="{ 'login-shell--dark': isDark }">
            <!-- Brand plane -->
            <section class="login-brand" aria-label="SkyDesk">
                <div class="login-brand__glow" aria-hidden="true" />
                <div class="login-brand__grid" aria-hidden="true" />

                <div class="login-brand__top">
                    <div
                        class="login-mark"
                        :style="{ background: primaryColor }"
                    >
                        ✦
                    </div>
                    <AppearanceMenu />
                </div>

                <div class="login-brand__hero">
                    <p class="login-brand__eyebrow">Рабочее пространство</p>
                    <h1 class="login-brand__title">SkyDesk</h1>
                    <p class="login-brand__lead">
                        Поручения, календарь и финансы — всё, что нужно личному помощнику за несколько секунд.
                    </p>
                </div>

                <ul v-if="mdAndUp" class="login-brand__points">
                    <li>Быстрая фиксация поручений</li>
                    <li>Календарь встреч и поездок</li>
                    <li>Авансы и отчётность</li>
                </ul>
            </section>

            <!-- Auth form -->
            <section class="login-panel">
                <div class="login-panel__inner">
                    <header class="login-panel__header">
                        <h2 class="login-panel__title">Вход</h2>
                        <p class="login-panel__subtitle">
                            Продолжите работу с поручениями
                        </p>
                    </header>

                    <v-alert
                        v-if="status"
                        type="success"
                        variant="tonal"
                        class="mb-4"
                        density="comfortable"
                    >
                        {{ status }}
                    </v-alert>

                    <v-alert
                        v-if="error"
                        type="error"
                        variant="tonal"
                        class="mb-4"
                        density="comfortable"
                    >
                        {{ error }}
                    </v-alert>

                    <v-alert
                        v-if="telegramLoading"
                        type="info"
                        variant="tonal"
                        class="mb-4"
                        density="comfortable"
                    >
                        Вход через Telegram…
                    </v-alert>

                    <v-form @submit.prevent="submit">
                        <v-text-field
                            v-model="form.email"
                            label="Email"
                            type="email"
                            autocomplete="username"
                            prepend-inner-icon="mdi-email-outline"
                            class="mb-1"
                            hide-details="auto"
                            :disabled="telegramLoading"
                        />

                        <v-text-field
                            v-model="form.password"
                            label="Пароль"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="current-password"
                            prepend-inner-icon="mdi-lock-outline"
                            :append-inner-icon="showPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
                            class="mb-2"
                            hide-details="auto"
                            :disabled="telegramLoading"
                            @click:append-inner="showPassword = !showPassword"
                        />

                        <div class="d-flex align-center justify-space-between mb-6 flex-wrap ga-2">
                            <v-checkbox
                                v-model="form.remember"
                                label="Запомнить меня"
                                density="compact"
                                color="primary"
                                hide-details
                                class="ms-n1"
                                :disabled="telegramLoading"
                            />
                        </div>

                        <v-btn
                            type="submit"
                            color="primary"
                            block
                            size="large"
                            height="52"
                            :loading="form.processing || telegramLoading"
                            :disabled="!canSubmit || telegramLoading"
                        >
                            Войти
                        </v-btn>
                    </v-form>
                </div>
            </section>
        </div>
    </v-app>
</template>

<style scoped>
.login-app :deep(.v-application__wrap) {
    min-height: 100dvh;
}

.login-shell {
    min-height: 100dvh;
    display: grid;
    grid-template-columns: 1fr;
    background: rgb(var(--v-theme-background));
    color: rgb(var(--v-theme-on-background));
    font-family: Manrope, system-ui, sans-serif;
}

.login-brand {
    position: relative;
    overflow: hidden;
    padding:
        calc(24px + env(safe-area-inset-top, 0px))
        24px
        28px
        24px;
    background:
        radial-gradient(120% 90% at 12% -10%, rgba(var(--v-theme-primary), 0.34), transparent 55%),
        radial-gradient(90% 70% at 100% 20%, rgba(255, 173, 77, 0.16), transparent 45%),
        linear-gradient(165deg, #1a1730 0%, #2a2450 48%, #191827 100%);
    color: #f4f2ff;
    animation: login-brand-in 700ms cubic-bezier(0.22, 1, 0.36, 1) both;
}

.login-shell--dark .login-brand {
    background:
        radial-gradient(120% 90% at 12% -10%, rgba(var(--v-theme-primary), 0.28), transparent 55%),
        radial-gradient(90% 70% at 100% 20%, rgba(255, 173, 77, 0.1), transparent 45%),
        linear-gradient(165deg, #0e0e12 0%, #17161f 48%, #101014 100%);
}

.login-brand__glow {
    position: absolute;
    width: 42vmax;
    height: 42vmax;
    right: -12vmax;
    bottom: -18vmax;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(var(--v-theme-primary), 0.45), transparent 68%);
    filter: blur(8px);
    animation: login-glow 12s ease-in-out infinite alternate;
    pointer-events: none;
}

.login-brand__grid {
    position: absolute;
    inset: 0;
    opacity: 0.18;
    background-image:
        linear-gradient(rgba(255, 255, 255, 0.07) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.07) 1px, transparent 1px);
    background-size: 36px 36px;
    mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.75), transparent 88%);
    pointer-events: none;
}

.login-brand__top {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 36px;
}

.login-brand__top :deep(.v-btn) {
    color: #f4f2ff;
    border-color: rgba(244, 242, 255, 0.35);
}

.login-mark {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    color: #fff;
    font-weight: 700;
    font-size: 18px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.18);
}

.login-brand__hero {
    position: relative;
    z-index: 1;
    max-width: 420px;
}

.login-brand__eyebrow {
    margin: 0 0 10px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: rgba(244, 242, 255, 0.62);
}

.login-brand__title {
    margin: 0;
    font-family: Fraunces, Georgia, serif;
    font-size: clamp(2.75rem, 8vw, 4.4rem);
    font-weight: 700;
    line-height: 0.95;
    letter-spacing: -0.04em;
}

.login-brand__lead {
    margin: 16px 0 0;
    max-width: 34ch;
    font-size: 1rem;
    line-height: 1.5;
    color: rgba(244, 242, 255, 0.78);
}

.login-brand__points {
    position: relative;
    z-index: 1;
    margin: 40px 0 0;
    padding: 0;
    list-style: none;
    display: grid;
    gap: 10px;
    color: rgba(244, 242, 255, 0.72);
    font-size: 0.92rem;
    font-weight: 600;
}

.login-brand__points li {
    display: flex;
    align-items: center;
    gap: 10px;
}

.login-brand__points li::before {
    content: '';
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: rgb(var(--v-theme-primary));
    box-shadow: 0 0 0 4px rgba(var(--v-theme-primary), 0.2);
}

.login-panel {
    display: flex;
    align-items: center;
    justify-content: center;
    padding:
        28px
        24px
        calc(28px + env(safe-area-inset-bottom, 0px));
    animation: login-panel-in 750ms cubic-bezier(0.22, 1, 0.36, 1) 80ms both;
}

.login-panel__inner {
    width: 100%;
    max-width: 400px;
}

.login-panel__header {
    margin-bottom: 28px;
}

.login-panel__title {
    margin: 0;
    font-family: Fraunces, Georgia, serif;
    font-size: 2rem;
    font-weight: 700;
    letter-spacing: -0.03em;
    line-height: 1.1;
}

.login-panel__subtitle {
    margin: 8px 0 0;
    color: rgba(var(--v-theme-on-surface), 0.62);
    font-size: 0.95rem;
}

.login-link {
    border: 0;
    background: transparent;
    color: rgb(var(--v-theme-primary));
    font: inherit;
    font-weight: 700;
    cursor: pointer;
    padding: 0;
}

.login-link:hover {
    text-decoration: underline;
}

@keyframes login-brand-in {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

@keyframes login-panel-in {
    from {
        opacity: 0;
        transform: translateY(18px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

@keyframes login-glow {
    from {
        transform: translate3d(0, 0, 0) scale(1);
    }
    to {
        transform: translate3d(-6%, -4%, 0) scale(1.08);
    }
}

@media (min-width: 960px) {
    .login-shell {
        grid-template-columns: minmax(0, 1.15fr) minmax(380px, 0.85fr);
    }

    .login-brand {
        min-height: 100dvh;
        padding: 40px 48px 48px;
        display: flex;
        flex-direction: column;
    }

    .login-brand__top {
        margin-bottom: auto;
    }

    .login-brand__hero {
        margin-top: 12vh;
    }

    .login-brand__points {
        margin-top: auto;
    }

    .login-panel {
        padding: 48px;
        border-left: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
        background: rgb(var(--v-theme-surface));
    }
}
</style>
