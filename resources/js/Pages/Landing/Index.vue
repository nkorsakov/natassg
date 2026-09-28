<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useTheme } from 'vuetify';
import AppearanceMenu from '@/Components/AppearanceMenu.vue';
import { useAppearance } from '@/composables/useAppearance';
import { reachGoal } from '@/analytics/metrika';

const page = usePage();
const theme = useTheme();
const { isDark } = useAppearance();

const isAuthenticated = computed(() => Boolean(page.props.auth?.user));
const primaryColor = computed(() => theme.current.value.colors.primary);
const appUrl = computed(() => {
    if (typeof window !== 'undefined') {
        return window.location.origin;
    }

    return '';
});

const accessOpen = ref(false);
const accessForm = useForm({
    name: '',
    phone: '',
    email: '',
    telegram: '',
    place: '',
    website: '',
});
const accessSent = ref(false);
const accessError = computed(() => {
    const { errors } = accessForm;

    return errors.phone || errors.email || errors.telegram || errors.name || '';
});

const preview = ref(null);

const heroImage = {
    src: '/images/landing/hero-main.jpg',
    alt: 'SkyDesk — поручения, календарь и финансы в одном пространстве',
};

const features = [
    {
        title: 'Фиксация за секунды',
        body: 'Поручение пришло в мессенджер, по телефону или устно — фиксируете за несколько касаний. Сразу со сроком, приоритетом и напоминанием. Больше ничего не теряется в чатах и блокнотах.',
        alt: 'SkyDesk — главная и список поручений на телефоне',
        src: '/images/landing/feature-tasks-duo.jpg',
        phone: false,
    },
    {
        title: 'Календарь рядом с работой',
        body: 'Встречи, поездки и дедлайны живут прямо рядом с поручениями. Один взгляд — и понятно, что сегодня и завтра. Никаких отдельных календарей и постоянного переключения между приложениями.',
        alt: 'SkyDesk — календарь',
        src: '/images/landing/feature-calendar.png',
        phone: false,
    },
    {
        title: 'Деньги на руках',
        body: 'Всегда видите, сколько реально доступно прямо сейчас. Авансы, расходы с фото чеков, остатки — всё прозрачно и в одном месте. Без Excel и без «а сколько у нас осталось?».',
        alt: 'SkyDesk — финансы, деньги на руках',
        src: '/images/landing/feature-finance.jpg',
        phone: false,
    },
    {
        title: 'Отчёт без созвона',
        body: 'Собрали итоги периода и отправили руководителю одну ссылку. Он видит задачи, события и финансы, когда ему удобно. Вы — без лишних статусов и звонков «ну что там по делам».',
        alt: 'SkyDesk — отчёт руководителю',
        src: '/images/landing/feature-report.jpg',
        phone: false,
    },
];

const seoTitle = 'Рабочее пространство личного помощника';
const seoDescription =
    'Поручения, календарь, деньги на руках и отчёты — всё в одном окне. 30 дней бесплатно, далее от 500 ₽/мес.';

const track = (goal, place) => {
    reachGoal(goal, place ? { place } : undefined);
};

const openAccess = (place = 'unknown') => {
    track('landing_request_access', place);
    accessForm.clearErrors();
    accessForm.place = place;
    accessSent.value = false;
    accessOpen.value = true;
};

const submitAccess = () => {
    accessForm.post('/access-request', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            track('landing_request_submit', accessForm.place);
            accessForm.reset();
            accessSent.value = true;
        },
    });
};

const openPreview = (item) => {
    preview.value = item;
};

const closePreview = () => {
    preview.value = null;
};
</script>

<template>
    <Head :title="seoTitle">
        <meta head-key="description" name="description" :content="seoDescription" />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta head-key="og:locale" property="og:locale" content="ru_RU" />
        <meta head-key="og:site_name" property="og:site_name" content="SkyDesk" />
        <meta head-key="og:title" property="og:title" content="SkyDesk — рабочее пространство личного помощника" />
        <meta head-key="og:description" property="og:description" :content="seoDescription" />
        <meta head-key="og:url" property="og:url" :content="appUrl || undefined" />
        <meta
            head-key="og:image"
            property="og:image"
            :content="appUrl ? `${appUrl}${heroImage.src}` : undefined"
        />
        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
        <meta head-key="twitter:title" name="twitter:title" content="SkyDesk — рабочее пространство личного помощника" />
        <meta
            head-key="twitter:description"
            name="twitter:description"
            :content="seoDescription"
        />
        <link
            v-if="appUrl"
            head-key="canonical"
            rel="canonical"
            :href="appUrl + '/'"
        />
    </Head>

    <v-app class="landing-app">
        <div class="landing" :class="{ 'landing--dark': isDark }">
            <div class="landing-glow landing-glow--a" aria-hidden="true" />
            <div class="landing-glow landing-glow--b" aria-hidden="true" />
            <div class="landing-glow landing-glow--c" aria-hidden="true" />

            <header class="landing-header">
                <div class="landing-container landing-header__inner">
                    <a href="/" class="landing-logo" aria-label="SkyDesk — на главную">
                        <span
                            class="landing-mark"
                            :style="{ background: primaryColor }"
                            aria-hidden="true"
                        >✦</span>
                        <span class="landing-logo__name">SkyDesk</span>
                    </a>

                    <div class="landing-header__actions">
                        <template v-if="isAuthenticated">
                            <Link
                                href="/dashboard"
                                class="landing-btn landing-btn--primary"
                                @click="track('landing_return', 'header')"
                            >
                                <span class="landing-btn__full">Вернуться в систему</span>
                                <span class="landing-btn__short">В систему</span>
                            </Link>
                        </template>
                        <template v-else>
                            <div class="landing-header__pair landing-btn--desktop">
                                <button
                                    type="button"
                                    class="landing-btn landing-btn--ghost"
                                    @click="openAccess('header')"
                                >
                                    Запросить доступ
                                </button>
                                <span class="landing-or" aria-hidden="true">или</span>
                                <Link
                                    href="/login?demo=1"
                                    class="landing-btn landing-btn--ghost"
                                    @click="track('landing_demo', 'header')"
                                >
                                    Демо доступ
                                </Link>
                            </div>
                            <Link
                                href="/login"
                                class="landing-btn landing-btn--primary"
                                @click="track('landing_login', 'header')"
                            >
                                Войти
                            </Link>
                        </template>
                        <div class="landing-header__theme">
                            <AppearanceMenu />
                        </div>
                    </div>
                </div>
            </header>

            <main class="landing-main">
                <section class="landing-hero" aria-labelledby="landing-hero-title">
                    <div class="landing-container landing-hero__inner">
                        <div class="landing-hero__copy">
                            <p class="landing-eyebrow">Рабочее пространство личного помощника</p>
                            <h1 id="landing-hero-title" class="landing-hero__title">
                                <span class="landing-hero__brand">SkyDesk</span>
                                <span class="landing-hero__line">Вся работа помощника — в одном окне</span>
                            </h1>
                            <p class="landing-hero__sub">
                                Фиксируйте поручения за секунды, держите календарь и авансы рядом,
                                отправляйте руководителю ссылку на отчёт — без блокнота и бесконечных чатов.
                            </p>
                            <p class="landing-hero__telegram">
                                <svg
                                    class="landing-hero__telegram-icon"
                                    viewBox="0 0 24 24"
                                    width="20"
                                    height="20"
                                    aria-hidden="true"
                                    focusable="false"
                                >
                                    <path
                                        fill="currentColor"
                                        d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"
                                    />
                                </svg>
                                <span>Уведомления в Telegram</span>
                            </p>
                            <div class="landing-hero__ctas">
                                <template v-if="isAuthenticated">
                                    <Link
                                        href="/dashboard"
                                        class="landing-btn landing-btn--primary landing-btn--lg"
                                        @click="track('landing_return', 'hero')"
                                    >
                                        Вернуться в систему
                                    </Link>
                                </template>
                                <template v-else>
                                    <div class="landing-header__pair">
                                        <button
                                            type="button"
                                            class="landing-btn landing-btn--primary landing-btn--lg"
                                            @click="openAccess('hero')"
                                        >
                                            Запросить доступ
                                        </button>
                                        <span class="landing-or" aria-hidden="true">или</span>
                                        <Link
                                            href="/login?demo=1"
                                            class="landing-btn landing-btn--ghost landing-btn--lg"
                                            @click="track('landing_demo', 'hero')"
                                        >
                                            Демо доступ
                                        </Link>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="landing-hero__visual">
                            <button
                                type="button"
                                class="landing-shot landing-shot--hero"
                                :aria-label="`Открыть: ${heroImage.alt}`"
                                @click="openPreview(heroImage)"
                            >
                                <img
                                    class="landing-shot__img"
                                    :src="heroImage.src"
                                    width="1200"
                                    height="900"
                                    :alt="heroImage.alt"
                                    loading="eager"
                                    decoding="async"
                                    fetchpriority="high"
                                >
                                <span class="landing-shot__hint" aria-hidden="true">
                                    <v-icon size="18">mdi-magnify-plus-outline</v-icon>
                                </span>
                            </button>
                        </div>
                    </div>
                </section>

                <section class="landing-features" aria-label="Возможности">
                    <div class="landing-container">
                        <article
                            v-for="(feature, index) in features"
                            :key="feature.title"
                            class="landing-feature"
                            :class="{ 'landing-feature--flip': index % 2 === 0 }"
                        >
                            <div class="landing-feature__copy">
                                <p class="landing-feature__index">{{ String(index + 1).padStart(2, '0') }}</p>
                                <h2>{{ feature.title }}</h2>
                                <p>{{ feature.body }}</p>
                            </div>

                            <div class="landing-feature__visual">
                                <button
                                    type="button"
                                    class="landing-stage"
                                    :class="feature.phone ? 'landing-stage--phone' : 'landing-stage--desk'"
                                    :aria-label="`Открыть: ${feature.alt}`"
                                    @click="openPreview(feature)"
                                >
                                    <span class="landing-stage__glow" aria-hidden="true" />
                                    <img
                                        class="landing-stage__img"
                                        :class="feature.phone ? 'landing-stage__img--phone' : 'landing-stage__img--desk'"
                                        :src="feature.src"
                                        :alt="feature.alt"
                                        width="960"
                                        height="720"
                                        loading="lazy"
                                        decoding="async"
                                    >
                                    <span class="landing-shot__hint" aria-hidden="true">
                                        <v-icon size="16">mdi-magnify-plus-outline</v-icon>
                                    </span>
                                </button>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="landing-pricing" aria-labelledby="landing-pricing-title">
                    <div class="landing-container">
                        <p class="landing-eyebrow landing-eyebrow--center">Тарифы</p>
                        <h2 id="landing-pricing-title" class="landing-pricing__title">Простая цена</h2>
                        <p class="landing-pricing__lead">
                            30 дней бесплатно на любом тарифе. Дальше — без скрытых платежей.
                        </p>

                        <div class="landing-price-grid">
                            <article class="landing-price-card">
                                <div class="landing-price-card__badge">30 дней бесплатно</div>
                                <h3 class="landing-price-card__name">Месяц</h3>
                                <div class="landing-price-card__amount">
                                    <span class="landing-price-card__value">500</span>
                                    <span class="landing-price-card__unit">₽ / мес</span>
                                </div>
                                <p class="landing-price-card__note">
                                    После пробного периода. Гибко, если хотите начать без годовой оплаты.
                                </p>
                                <ul class="landing-price-card__list">
                                    <li>30 дней — без оплаты</li>
                                    <li>Дальше 500 ₽ в месяц</li>
                                    <li>Всё рабочее пространство</li>
                                </ul>
                            </article>

                            <article class="landing-price-card landing-price-card--featured">
                                <div class="landing-price-card__badge landing-price-card__badge--accent">
                                    Выгоднее · −17%
                                </div>
                                <h3 class="landing-price-card__name">Год</h3>
                                <div class="landing-price-card__amount">
                                    <span class="landing-price-card__value">5 000</span>
                                    <span class="landing-price-card__unit">₽ / год</span>
                                </div>
                                <p class="landing-price-card__note">
                                    Вместо 6 000 ₽ при оплате помесячно. 30 дней пробного периода сохраняются.
                                </p>
                                <ul class="landing-price-card__list">
                                    <li>30 дней — без оплаты</li>
                                    <li>~417 ₽ в месяц</li>
                                    <li>Доп. модули — по запросу</li>
                                </ul>
                            </article>
                        </div>

                        <div class="landing-price-actions">
                            <template v-if="isAuthenticated">
                                <Link
                                    href="/dashboard"
                                    class="landing-btn landing-btn--primary landing-btn--lg"
                                    @click="track('landing_return', 'pricing')"
                                >
                                    Вернуться в систему
                                </Link>
                            </template>
                            <template v-else>
                                <div class="landing-header__pair">
                                    <button
                                        type="button"
                                        class="landing-btn landing-btn--primary landing-btn--lg"
                                        @click="openAccess('pricing')"
                                    >
                                        Запросить доступ
                                    </button>
                                    <span class="landing-or" aria-hidden="true">или</span>
                                    <Link
                                        href="/login?demo=1"
                                        class="landing-btn landing-btn--ghost landing-btn--lg"
                                        @click="track('landing_demo', 'pricing')"
                                    >
                                        Демо доступ
                                    </Link>
                                </div>
                            </template>
                        </div>
                    </div>
                </section>

                <section class="landing-modules" aria-labelledby="landing-modules-title">
                    <div class="landing-container">
                        <h2 id="landing-modules-title">Нужны дополнительные возможности?</h2>
                        <p>
                            Платформа модульная. Дополнительные модули разрабатываются по запросу
                            под конкретные процессы клиента.
                        </p>
                    </div>
                </section>

                <section class="landing-closing" aria-labelledby="landing-closing-title">
                    <div class="landing-container">
                        <h2 id="landing-closing-title">
                            Готовы убрать блокнот и чаты<br>из ежедневной работы?
                        </h2>
                        <p>SkyDesk — для личных и executive-ассистентов, которым нужно всё в одном окне.</p>
                        <div class="landing-hero__ctas landing-hero__ctas--center">
                            <template v-if="isAuthenticated">
                                <Link
                                    href="/dashboard"
                                    class="landing-btn landing-btn--primary landing-btn--lg"
                                    @click="track('landing_return', 'closing')"
                                >
                                    Вернуться в систему
                                </Link>
                            </template>
                            <template v-else>
                                <div class="landing-header__pair">
                                    <button
                                        type="button"
                                        class="landing-btn landing-btn--primary landing-btn--lg"
                                        @click="openAccess('closing')"
                                    >
                                        Запросить доступ
                                    </button>
                                    <span class="landing-or" aria-hidden="true">или</span>
                                    <Link
                                        href="/login?demo=1"
                                        class="landing-btn landing-btn--ghost landing-btn--lg"
                                        @click="track('landing_demo', 'closing')"
                                    >
                                        Демо доступ
                                    </Link>
                                </div>
                            </template>
                        </div>
                    </div>
                </section>
            </main>

            <footer class="landing-footer">
                <div class="landing-container">
                    SkyDesk · Рабочее пространство личного помощника · {{ new Date().getFullYear() }}
                </div>
            </footer>
        </div>

        <v-dialog
            :model-value="Boolean(preview)"
            fullscreen
            transition="fade-transition"
            scrim="rgba(10, 10, 14, 0.82)"
            @update:model-value="(v) => !v && closePreview()"
        >
            <div
                v-if="preview"
                class="landing-lightbox"
                role="dialog"
                aria-modal="true"
                :aria-label="preview.alt"
                @click.self="closePreview"
            >
                <button
                    type="button"
                    class="landing-lightbox__close"
                    aria-label="Закрыть"
                    @click="closePreview"
                >
                    <v-icon size="28">mdi-close</v-icon>
                </button>
                <img
                    class="landing-lightbox__img"
                    :src="preview.src"
                    :alt="preview.alt"
                    @click.stop
                >
                <p class="landing-lightbox__caption">{{ preview.alt }}</p>
            </div>
        </v-dialog>

        <v-dialog
            v-if="!isAuthenticated"
            v-model="accessOpen"
            max-width="420"
            scrim="black"
        >
            <v-card class="landing-access pa-6">
                <h2 class="landing-access__title">Запросить доступ</h2>
                <template v-if="accessSent">
                    <v-alert
                        type="success"
                        variant="tonal"
                        density="comfortable"
                        class="mb-4"
                    >
                        Заявка отправлена — свяжемся с вами в ближайшее время.
                    </v-alert>

                    <div class="d-flex justify-end">
                        <v-btn color="primary" @click="accessOpen = false">Готово</v-btn>
                    </div>
                </template>

                <form v-else @submit.prevent="submitAccess">
                    <p class="landing-access__sub">
                        Оставьте удобный способ связи — напишем и откроем доступ.
                    </p>

                    <v-text-field
                        v-model="accessForm.name"
                        label="Имя"
                        autocomplete="name"
                        prepend-inner-icon="mdi-account-outline"
                        class="mb-1"
                        hide-details="auto"
                    />
                    <v-text-field
                        v-model="accessForm.telegram"
                        label="Telegram"
                        placeholder="@username или t.me/username"
                        prepend-inner-icon="mdi-send-outline"
                        class="mb-1"
                        hide-details="auto"
                        :error="Boolean(accessForm.errors.telegram)"
                    />
                    <v-text-field
                        v-model="accessForm.phone"
                        label="Телефон"
                        type="tel"
                        autocomplete="tel"
                        prepend-inner-icon="mdi-phone-outline"
                        class="mb-1"
                        hide-details="auto"
                        :error="Boolean(accessForm.errors.phone)"
                    />
                    <v-text-field
                        v-model="accessForm.email"
                        label="Email"
                        type="email"
                        autocomplete="email"
                        prepend-inner-icon="mdi-email-outline"
                        class="mb-4"
                        hide-details="auto"
                        :error="Boolean(accessForm.errors.email)"
                    />

                    <input
                        v-model="accessForm.website"
                        type="text"
                        name="website"
                        tabindex="-1"
                        autocomplete="off"
                        aria-hidden="true"
                        class="landing-access__trap"
                    >

                    <v-alert
                        v-if="accessError"
                        type="error"
                        variant="tonal"
                        density="comfortable"
                        class="mb-4"
                    >
                        {{ accessError }}
                    </v-alert>

                    <div class="d-flex ga-2 justify-end flex-wrap">
                        <v-btn variant="text" @click="accessOpen = false">Закрыть</v-btn>
                        <v-btn
                            type="submit"
                            color="primary"
                            :loading="accessForm.processing"
                        >
                            Отправить
                        </v-btn>
                    </div>
                </form>
            </v-card>
        </v-dialog>
    </v-app>
</template>

<style scoped>
.landing-app :deep(.v-application__wrap) {
    min-height: 100dvh;
}

.landing {
    position: relative;
    isolation: isolate;
    min-height: 100dvh;
    overflow-x: clip;
    background: rgb(var(--v-theme-background));
    color: rgb(var(--v-theme-on-background));
    font-family: Manrope, system-ui, sans-serif;
}

.landing-glow {
    position: fixed;
    border-radius: 50%;
    filter: blur(48px);
    pointer-events: none;
    z-index: 0;
}

.landing-glow--a {
    width: 420px;
    height: 420px;
    top: -120px;
    left: -80px;
    background: rgba(105, 87, 238, 0.22);
}

.landing-glow--b {
    width: 360px;
    height: 360px;
    top: 28%;
    right: -100px;
    background: rgba(255, 173, 77, 0.16);
}

.landing-glow--c {
    width: 300px;
    height: 300px;
    bottom: 12%;
    left: 28%;
    background: rgba(55, 168, 120, 0.12);
}

.landing--dark .landing-glow--a {
    background: rgba(105, 87, 238, 0.28);
}

.landing--dark .landing-glow--b {
    background: rgba(255, 173, 77, 0.14);
}

.landing--dark .landing-glow--c {
    background: rgba(55, 168, 120, 0.1);
}

.landing-header,
.landing-main,
.landing-footer {
    position: relative;
    z-index: 1;
}


.landing-container {
    width: min(960px, calc(100% - 32px));
    margin-inline: auto;
}

.landing-header {
    position: sticky;
    top: 0;
    z-index: 40;
    backdrop-filter: blur(14px);
    background: rgba(var(--v-theme-surface), 0.78);
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    padding:
        calc(10px + env(safe-area-inset-top, 0px))
        0
        10px;
}

.landing-header__inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.landing-logo {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    color: inherit;
    min-width: 0;
}

.landing-mark {
    width: 34px;
    height: 34px;
    border-radius: 11px;
    display: grid;
    place-items: center;
    color: #fff;
    font-weight: 700;
    font-size: 15px;
    box-shadow: 0 8px 22px rgba(0, 0, 0, 0.14);
    flex-shrink: 0;
}

.landing-logo__name {
    font-family: Fraunces, Georgia, serif;
    font-weight: 700;
    font-size: 1.2rem;
    letter-spacing: -0.03em;
}

.landing-header__actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
    margin-left: auto;
}

.landing-header__pair {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.landing-or {
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: lowercase;
    color: rgba(var(--v-theme-on-surface), 0.42);
    user-select: none;
}

.landing-header__theme {
    display: flex;
    align-items: center;
    margin-left: 4px;
    padding-left: 10px;
    border-left: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 0.9));
}

.landing-header__theme :deep(.v-btn) {
    opacity: 0.48;
    color: rgba(var(--v-theme-on-surface), 0.55) !important;
    border-color: rgba(var(--v-border-color), 0.35) !important;
    box-shadow: none !important;
}

.landing-header__theme :deep(.v-btn:hover),
.landing-header__theme :deep(.v-btn[aria-expanded='true']) {
    opacity: 0.78;
}

.landing-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 16px;
    border-radius: 12px;
    font: inherit;
    font-weight: 700;
    font-size: 0.9rem;
    text-decoration: none;
    border: 1px solid transparent;
    cursor: pointer;
    white-space: nowrap;
    transition:
        background 0.15s ease,
        border-color 0.15s ease,
        filter 0.15s ease;
}

.landing-btn--primary {
    background: rgb(var(--v-theme-primary));
    color: rgb(var(--v-theme-on-primary));
}

.landing-btn--primary:hover {
    filter: brightness(1.05);
}

.landing-btn--ghost {
    background: transparent;
    color: rgb(var(--v-theme-on-surface));
    border-color: rgba(var(--v-border-color), var(--v-border-opacity));
}

.landing-btn--ghost:hover {
    background: rgba(var(--v-theme-on-surface), 0.04);
}

.landing-btn--lg {
    padding: 13px 20px;
    font-size: 0.98rem;
}

.landing-btn__short {
    display: none;
}

.landing-hero {
    padding: 40px 0 48px;
    animation: landing-in 700ms cubic-bezier(0.22, 1, 0.36, 1) both;
}

.landing-hero__inner {
    display: grid;
    gap: 28px;
    align-items: center;
}

.landing-eyebrow {
    margin: 0 0 12px;
    display: inline-block;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: rgb(var(--v-theme-primary));
}

.landing-hero__title {
    margin: 0;
    display: grid;
    gap: 10px;
}

.landing-hero__brand {
    font-family: Fraunces, Georgia, serif;
    font-size: clamp(2.4rem, 9vw, 3.8rem);
    font-weight: 700;
    line-height: 0.95;
    letter-spacing: -0.04em;
}

.landing-hero__line {
    font-family: Fraunces, Georgia, serif;
    font-size: clamp(1.25rem, 3.8vw, 1.75rem);
    font-weight: 700;
    line-height: 1.2;
    letter-spacing: -0.03em;
    max-width: 18ch;
}

.landing-hero__sub {
    margin: 14px 0 0;
    max-width: 36ch;
    font-size: 0.98rem;
    line-height: 1.55;
    color: rgba(var(--v-theme-on-surface), 0.68);
}

.landing-hero__telegram {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin: 16px 0 0;
    padding: 8px 12px;
    border-radius: 999px;
    font-size: 0.88rem;
    font-weight: 700;
    color: rgb(var(--v-theme-on-surface));
    background: rgba(var(--v-theme-surface), 0.85);
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    box-shadow: 0 8px 20px -16px rgba(0, 0, 0, 0.25);
}

.landing-hero__telegram-icon {
    flex-shrink: 0;
    color: #2AABEE;
    display: block;
}

.landing-hero__ctas {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 22px;
}

.landing-hero__ctas--center {
    justify-content: center;
    margin-top: 0;
}

.landing-hero__visual {
    display: grid;
    place-items: center;
}

.landing-shot {
    position: relative;
    display: block;
    padding: 0;
    border: 0;
    background: transparent;
    cursor: zoom-in;
    border-radius: 20px;
    max-width: min(100%, 420px);
    width: 100%;
}

.landing-shot--hero {
    max-width: min(100%, 460px);
}

.landing-shot__img {
    display: block;
    width: 100%;
    height: auto;
    border-radius: 20px;
    box-shadow: 0 20px 44px -16px rgba(0, 0, 0, 0.28);
    animation: landing-phone-in 850ms cubic-bezier(0.22, 1, 0.36, 1) 80ms both;
}

.landing-shot__hint {
    position: absolute;
    right: 8px;
    bottom: 8px;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    color: #fff;
    background: rgba(20, 20, 28, 0.55);
    backdrop-filter: blur(8px);
    opacity: 0.9;
    pointer-events: none;
}

.landing-features {
    padding: 12px 0 56px;
}

.landing-feature {
    display: grid;
    gap: 18px;
    align-items: center;
    margin-bottom: 40px;
    padding-bottom: 40px;
    border-bottom: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 0.85));
}

.landing-feature:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: 0;
}

.landing-feature__index {
    margin: 0 0 8px;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    color: rgba(var(--v-theme-on-surface), 0.38);
}

.landing-feature__copy h2 {
    margin: 0 0 8px;
    font-family: Fraunces, Georgia, serif;
    font-size: clamp(1.25rem, 2.8vw, 1.55rem);
    font-weight: 700;
    letter-spacing: -0.03em;
    line-height: 1.15;
}

.landing-feature__copy > p:not(.landing-feature__index) {
    margin: 0;
    color: rgba(var(--v-theme-on-surface), 0.68);
    font-size: 0.96rem;
    line-height: 1.55;
    max-width: 40ch;
}

.landing-feature__visual {
    display: grid;
    place-items: center;
}

.landing-stage {
    position: relative;
    display: grid;
    place-items: center;
    width: 100%;
    max-width: 420px;
    margin: 0 auto;
    padding: 14px;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 18px;
    overflow: hidden;
    cursor: zoom-in;
    background:
        radial-gradient(90% 80% at 8% 0%, rgba(105, 87, 238, 0.16), transparent 55%),
        radial-gradient(70% 70% at 100% 20%, rgba(255, 173, 77, 0.1), transparent 50%),
        rgba(var(--v-theme-surface), 0.72);
    box-shadow: 0 12px 28px -18px rgba(0, 0, 0, 0.28);
    transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.landing--dark .landing-stage {
    background:
        radial-gradient(90% 80% at 8% 0%, rgba(105, 87, 238, 0.22), transparent 55%),
        radial-gradient(70% 70% at 100% 20%, rgba(255, 173, 77, 0.08), transparent 50%),
        rgba(var(--v-theme-surface), 0.55);
}

.landing-stage:hover {
    transform: translateY(-1px);
    box-shadow: 0 16px 32px -16px rgba(0, 0, 0, 0.32);
}

.landing-stage:hover .landing-shot__hint,
.landing-shot:hover .landing-shot__hint {
    background: rgba(20, 20, 28, 0.72);
}

.landing-stage__glow {
    position: absolute;
    inset: auto auto -30% -15%;
    width: 50%;
    height: 50%;
    border-radius: 50%;
    background: rgba(105, 87, 238, 0.16);
    filter: blur(28px);
    pointer-events: none;
}

.landing-stage__img {
    position: relative;
    z-index: 1;
    display: block;
    height: auto;
}

.landing-stage__img--phone {
    width: min(168px, 46vw);
    border-radius: 16px;
    box-shadow: 0 12px 28px -12px rgba(0, 0, 0, 0.32);
}

.landing-stage__img--desk {
    width: 100%;
    max-width: 360px;
    max-height: 200px;
    object-fit: contain;
    object-position: center top;
    border-radius: 10px;
    box-shadow: 0 10px 24px -12px rgba(0, 0, 0, 0.28);
}

.landing-stage--phone {
    max-width: 240px;
    padding: 16px;
}

.landing-stage--desk {
    max-width: 400px;
}

.landing-stage .landing-shot__hint {
    z-index: 2;
}

.landing-eyebrow--center {
    display: block;
    text-align: center;
}

.landing-pricing {
    padding: 56px 0 48px;
    text-align: center;
}

.landing-pricing__title {
    margin: 0 0 10px;
    font-family: Fraunces, Georgia, serif;
    font-size: clamp(1.4rem, 3vw, 1.85rem);
    font-weight: 700;
    letter-spacing: -0.03em;
}

.landing-pricing__lead {
    margin: 0 auto 28px;
    max-width: 40ch;
    color: rgba(var(--v-theme-on-surface), 0.68);
    font-size: 0.96rem;
    line-height: 1.55;
}

.landing-price-grid {
    display: grid;
    gap: 16px;
    max-width: 760px;
    margin: 0 auto;
}

.landing-price-card {
    position: relative;
    padding: 24px 22px 22px;
    border-radius: 22px;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    background:
        radial-gradient(90% 80% at 10% 0%, rgba(105, 87, 238, 0.12), transparent 55%),
        rgba(var(--v-theme-surface), 0.78);
    box-shadow: 0 14px 32px -22px rgba(0, 0, 0, 0.28);
    text-align: left;
}

.landing-price-card--featured {
    background:
        radial-gradient(90% 80% at 10% 0%, rgba(105, 87, 238, 0.2), transparent 55%),
        radial-gradient(70% 70% at 100% 30%, rgba(255, 173, 77, 0.12), transparent 50%),
        rgba(var(--v-theme-surface), 0.88);
    border-color: rgba(var(--v-theme-primary), 0.35);
    box-shadow:
        0 0 0 1px rgba(var(--v-theme-primary), 0.12),
        0 16px 36px -20px rgba(0, 0, 0, 0.3);
}

.landing-price-card__badge {
    display: inline-flex;
    align-items: center;
    margin-bottom: 12px;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.12);
}

.landing-price-card__badge--accent {
    color: #8a4b00;
    background: rgba(255, 173, 77, 0.28);
}

.landing--dark .landing-price-card__badge--accent {
    color: #ffd9a8;
    background: rgba(255, 173, 77, 0.18);
}

.landing-price-card__name {
    margin: 0 0 8px;
    font-family: Fraunces, Georgia, serif;
    font-size: 1.2rem;
    font-weight: 700;
    letter-spacing: -0.02em;
}

.landing-price-card__amount {
    display: flex;
    align-items: baseline;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}

.landing-price-card__value {
    font-family: Fraunces, Georgia, serif;
    font-size: clamp(2.3rem, 7vw, 3rem);
    font-weight: 700;
    line-height: 0.95;
    letter-spacing: -0.04em;
}

.landing-price-card__unit {
    font-size: 0.95rem;
    font-weight: 700;
    color: rgba(var(--v-theme-on-surface), 0.58);
}

.landing-price-card__note {
    margin: 0 0 16px;
    color: rgba(var(--v-theme-on-surface), 0.68);
    font-size: 0.92rem;
    line-height: 1.5;
}

.landing-price-card__list {
    margin: 0;
    padding: 0;
    list-style: none;
    display: grid;
    gap: 9px;
}

.landing-price-card__list li {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 0.92rem;
    font-weight: 600;
    line-height: 1.4;
}

.landing-price-card__list li::before {
    content: '';
    width: 7px;
    height: 7px;
    margin-top: 7px;
    border-radius: 50%;
    flex-shrink: 0;
    background: rgb(var(--v-theme-primary));
    box-shadow: 0 0 0 4px rgba(var(--v-theme-primary), 0.16);
}

.landing-price-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px;
    margin-top: 24px;
}

.landing-modules {
    padding: 44px 0;
    text-align: center;
    border-block: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    background: rgba(var(--v-theme-surface), 0.55);
    backdrop-filter: blur(8px);
}

.landing-modules h2 {
    margin: 0 0 10px;
    font-family: Fraunces, Georgia, serif;
    font-size: 1.3rem;
    font-weight: 700;
    letter-spacing: -0.02em;
}

.landing-modules p {
    margin: 0 auto;
    max-width: 40ch;
    color: rgba(var(--v-theme-on-surface), 0.68);
    line-height: 1.55;
    font-size: 0.96rem;
}

.landing-closing {
    padding: 56px 0;
    text-align: center;
}

.landing-closing h2 {
    margin: 0 0 10px;
    font-family: Fraunces, Georgia, serif;
    font-size: clamp(1.3rem, 3vw, 1.75rem);
    font-weight: 700;
    letter-spacing: -0.03em;
    line-height: 1.2;
}

.landing-closing p {
    margin: 0 0 24px;
    color: rgba(var(--v-theme-on-surface), 0.68);
    font-size: 0.96rem;
}

.landing-footer {
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    padding: 22px 0 calc(22px + env(safe-area-inset-bottom, 0px));
    text-align: center;
    font-size: 0.85rem;
    color: rgba(var(--v-theme-on-surface), 0.55);
}

.landing-access__title {
    margin: 0;
    font-family: Fraunces, Georgia, serif;
    font-size: 1.6rem;
    font-weight: 700;
    letter-spacing: -0.03em;
}

.landing-access__sub {
    margin: 8px 0 20px;
    color: rgba(var(--v-theme-on-surface), 0.62);
    font-size: 0.95rem;
}

.landing-access__trap {
    position: absolute;
    left: -9999px;
    width: 1px;
    height: 1px;
    opacity: 0;
}

.landing-lightbox {
    min-height: 100dvh;
    display: grid;
    grid-template-rows: auto 1fr auto;
    align-items: center;
    justify-items: center;
    gap: 12px;
    padding:
        calc(16px + env(safe-area-inset-top, 0px))
        16px
        calc(16px + env(safe-area-inset-bottom, 0px));
    background: transparent;
}

.landing-lightbox__close {
    justify-self: end;
    width: 44px;
    height: 44px;
    border: 0;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
    cursor: pointer;
    display: grid;
    place-items: center;
}

.landing-lightbox__img {
    max-width: min(1100px, 100%);
    max-height: min(78dvh, 900px);
    width: auto;
    height: auto;
    object-fit: contain;
    border-radius: 16px;
    box-shadow: 0 24px 64px rgba(0, 0, 0, 0.45);
    background: rgb(var(--v-theme-surface));
}

.landing-lightbox__caption {
    margin: 0;
    color: rgba(255, 255, 255, 0.78);
    font-size: 0.92rem;
    text-align: center;
    max-width: 40ch;
}

@keyframes landing-in {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

@keyframes landing-phone-in {
    from {
        opacity: 0;
        transform: translateY(22px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

@media (max-width: 719px) {
    .landing-btn--desktop {
        display: none;
    }

    .landing-hero__visual {
        display: none;
    }

    .landing-header__theme {
        margin-left: 0;
        padding-left: 6px;
    }

    .landing-header__pair {
        width: 100%;
        justify-content: center;
    }

    .landing-btn__full {
        display: none;
    }

    .landing-btn__short {
        display: inline;
    }

    .landing-hero__ctas .landing-btn {
        flex: 1 1 auto;
        min-height: 46px;
    }

    .landing-price-card__cta .landing-btn {
        flex: 1 1 auto;
        min-height: 46px;
    }

    .landing-price-actions .landing-btn {
        flex: 1 1 auto;
        min-height: 46px;
    }

    .landing-feature {
        margin-bottom: 32px;
        padding-bottom: 32px;
    }

    .landing-stage--phone {
        max-width: 200px;
        padding: 12px;
    }

    .landing-stage__img--phone {
        width: min(148px, 42vw);
    }

    .landing-stage--desk {
        max-width: 100%;
    }

    .landing-stage__img--desk {
        max-width: 100%;
        max-height: 170px;
    }

    .landing-lightbox__img {
        max-height: 72dvh;
        border-radius: 12px;
    }
}

@media (min-width: 720px) {
    .landing-container {
        width: min(960px, calc(100% - 48px));
    }

    .landing-price-grid {
        grid-template-columns: 1fr 1fr;
        align-items: stretch;
        gap: 18px;
    }
}

@media (min-width: 900px) {
    .landing-hero {
        padding: 52px 0 64px;
    }

    .landing-hero__inner {
        grid-template-columns: minmax(0, 1fr) minmax(280px, 1.05fr);
        gap: 36px;
    }

    .landing-shot--hero {
        max-width: 520px;
    }

    .landing-feature {
        grid-template-columns: minmax(0, 1.15fr) minmax(220px, 0.85fr);
        gap: 36px;
        margin-bottom: 36px;
        padding-bottom: 36px;
    }

    .landing-feature--flip .landing-feature__copy {
        order: 2;
    }

    .landing-feature--flip .landing-feature__visual {
        order: 1;
    }

    .landing-feature__copy {
        padding-right: 8px;
    }

    .landing-feature--flip .landing-feature__copy {
        padding-right: 0;
        padding-left: 8px;
    }

    .landing-stage--phone {
        max-width: 220px;
    }

    .landing-stage__img--phone {
        width: 168px;
    }

    .landing-stage--desk {
        max-width: 380px;
    }

    .landing-stage__img--desk {
        max-width: 340px;
        max-height: 200px;
    }
}

</style>
