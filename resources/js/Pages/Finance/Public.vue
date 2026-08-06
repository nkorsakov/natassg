<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useTheme } from 'vuetify';
import AppearanceMenu from '@/Components/AppearanceMenu.vue';
import PinDialog from '@/Components/PinDialog.vue';
import { dictChipStyle } from '@/utils/dictColor';

const props = defineProps({
    unlocked: { type: Boolean, default: false },
    finance: { type: Object, default: null },
    unavailable: { type: Boolean, default: false },
});

const theme = useTheme();
const primaryColor = computed(() => theme.current.value.colors.primary);
const isDark = computed(() => !!theme.current.value.dark);

const showPin = ref(!props.unlocked && !props.unavailable);
const form = useForm({ pin: '' });

watch(
    () => props.unlocked,
    (v) => {
        if (v) showPin.value = false;
    },
);

const pinError = computed(() => form.errors.pin || '');

const formatMoney = (n) => `${Number(n || 0).toLocaleString('ru-RU')} ₽`;

const wallet = computed(() => props.finance?.wallet || {});

const totalTile = computed(() => ({
    label: 'На руках',
    value: formatMoney(wallet.value.on_hand),
    color: '#6957EE',
    bg: 'rgba(105, 87, 238, 0.12)',
    icon: 'mdi-hand-coin-outline',
}));

const partTiles = computed(() => [
    {
        key: 'wallet',
        label: 'Кошелёк',
        value: formatMoney(wallet.value.wallet),
        color: '#5B8DEF',
        bg: 'rgba(91, 141, 239, 0.14)',
        icon: 'mdi-wallet-outline',
        op: null,
    },
    {
        key: 'advances',
        label: 'В авансах',
        value: formatMoney(wallet.value.in_advances),
        color: '#0D9488',
        bg: 'rgba(13, 148, 136, 0.14)',
        icon: 'mdi-cash-multiple',
        op: '+',
    },
    {
        key: 'unassigned',
        label: 'Не разнесено',
        value: formatMoney(wallet.value.unassigned),
        color: '#E96667',
        bg: 'rgba(233, 102, 103, 0.14)',
        icon: 'mdi-help-circle-outline',
        op: '−',
    },
]);

const movementTone = (tx) => {
    if (tx.type === 'income' || Number(tx.amount) > 0) return 'in';
    if (tx.type === 'expense' || Number(tx.amount) < 0) return 'out';
    return 'xfer';
};

const movementSign = (tx) => {
    const amount = Number(tx.amount || 0);
    if (tx.type === 'income' || amount > 0) return '+';
    if (amount < 0) return '';
    if (tx.type === 'expense') return '−';
    return '';
};

const refreshedLabel = computed(() => {
    if (!props.finance?.refreshed_at) return '';
    return new Date(props.finance.refreshed_at).toLocaleString('ru-RU', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
});

const openPin = () => {
    form.clearErrors();
    form.reset('pin');
    showPin.value = true;
};

const closePin = () => {
    showPin.value = false;
    form.clearErrors();
    form.reset('pin');
};

const unlock = (pin) => {
    form.pin = pin;
    form.post('/cashflow/unlock', {
        preserveScroll: true,
        onSuccess: () => {
            showPin.value = false;
            form.reset('pin');
        },
    });
};

const lock = () => {
    router.post('/cashflow/lock');
};

const refresh = () => {
    router.reload({ only: ['finance', 'unlocked', 'unavailable'], preserveScroll: true });
};
</script>

<template>
    <v-app>
        <Head title="Деньги" />

        <div class="cash-public" :class="{ 'cash-public--dark': isDark }">
            <div class="cash-public__glow cash-public__glow--a" aria-hidden="true" />
            <div class="cash-public__glow cash-public__glow--b" aria-hidden="true" />
            <div class="cash-public__glow cash-public__glow--c" aria-hidden="true" />

            <v-app-bar flat color="background" density="comfortable" border class="cash-public__bar">
                <div class="d-flex align-center ga-2 ps-4">
                    <div class="skydesk-mark skydesk-mark--sm" :style="{ background: primaryColor }">✦</div>
                    <div>
                        <div class="skydesk-wordmark skydesk-wordmark--mobile">SkyDesk</div>
                        <div class="text-caption text-medium-emphasis" style="line-height:1.1">
                            Деньги · live
                        </div>
                    </div>
                </div>
                <template #append>
                    <div class="d-flex align-center ga-1 pe-2">
                        <v-btn
                            v-if="unlocked"
                            icon
                            variant="text"
                            size="small"
                            title="Обновить"
                            @click="refresh"
                        >
                            <v-icon>mdi-refresh</v-icon>
                        </v-btn>
                        <v-btn
                            v-if="unlocked"
                            icon
                            variant="text"
                            size="small"
                            title="Закрыть доступ"
                            @click="lock"
                        >
                            <v-icon>mdi-lock-outline</v-icon>
                        </v-btn>
                        <AppearanceMenu />
                    </div>
                </template>
            </v-app-bar>

            <v-main>
                <v-container class="pa-4 pa-md-8" style="max-width:860px">
                    <template v-if="!unlocked">
                        <v-card class="pa-6 mx-auto text-center" style="max-width:420px">
                            <div class="text-h6 font-weight-bold mb-1" style="font-family:Fraunces,Georgia,serif">
                                Доступ к деньгам
                            </div>
                            <p class="text-body-2 text-medium-emphasis mb-4">
                                Введите код руководителя, чтобы увидеть остатки и движение в реальном времени.
                            </p>
                            <v-alert
                                v-if="unavailable"
                                type="warning"
                                variant="tonal"
                                density="compact"
                                class="mb-4 text-left"
                            >
                                Источник финансов не настроен.
                            </v-alert>
                            <v-btn
                                color="primary"
                                size="large"
                                prepend-icon="mdi-lock-open-outline"
                                :disabled="unavailable"
                                @click="openPin"
                            >
                                Ввести код
                            </v-btn>
                        </v-card>
                    </template>

                    <template v-else-if="finance">
                        <header class="cash-public__hero mb-5">
                            <div class="cash-public__eyebrow">Live cashflow</div>
                            <h1 class="cash-public__title mb-2">Деньги сейчас</h1>
                            <p v-if="finance.subject?.name" class="text-body-2 mb-1">
                                {{ finance.subject.name }}
                                <span v-if="finance.subject.role" class="text-medium-emphasis">
                                    · {{ finance.subject.role }}
                                </span>
                            </p>
                            <p v-if="refreshedLabel" class="text-caption text-medium-emphasis mb-0">
                                Обновлено {{ refreshedLabel }}
                            </p>
                        </header>

                        <div class="cash-public__sum mb-5">
                            <div
                                class="cash-public__total"
                                :style="{ '--tile-bg': totalTile.bg, '--tile-color': totalTile.color }"
                            >
                                <div class="cash-public__total-top">
                                    <div class="cash-public__tile-icon cash-public__tile-icon--lg">
                                        <v-icon size="20" :icon="totalTile.icon" />
                                    </div>
                                    <div class="cash-public__total-copy">
                                        <div class="cash-public__tile-label">{{ totalTile.label }}</div>
                                        <div class="cash-public__total-hint">
                                            Кошелёк + В авансах − Не разнесено
                                        </div>
                                    </div>
                                </div>
                                <div class="cash-public__total-value">{{ totalTile.value }}</div>
                            </div>

                            <div class="cash-public__parts" aria-label="Состав суммы на руках">
                                <template v-for="tile in partTiles" :key="tile.key">
                                    <div
                                        v-if="tile.op"
                                        class="cash-public__op"
                                        aria-hidden="true"
                                    >
                                        {{ tile.op }}
                                    </div>
                                    <div
                                        class="cash-public__tile"
                                        :style="{ '--tile-bg': tile.bg, '--tile-color': tile.color }"
                                    >
                                        <div class="cash-public__tile-icon">
                                            <v-icon size="18" :icon="tile.icon" />
                                        </div>
                                        <div class="cash-public__tile-body">
                                            <div class="cash-public__tile-label">{{ tile.label }}</div>
                                            <div class="cash-public__tile-value">{{ tile.value }}</div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <section class="mb-5">
                            <h2 class="text-subtitle-1 font-weight-bold mb-3">Открытые авансы</h2>
                            <v-card class="pa-2">
                                <div v-if="!(finance.advances || []).length" class="text-caption text-medium-emphasis pa-3">
                                    Нет открытых авансов.
                                </div>
                                <div
                                    v-for="a in finance.advances || []"
                                    :key="a.id"
                                    class="d-flex align-center justify-space-between ga-3 pa-3"
                                    style="border-bottom:1px solid rgba(var(--v-border-color),var(--v-border-opacity))"
                                >
                                    <div class="min-w-0">
                                        <div class="text-body-2 font-weight-medium">{{ a.title }}</div>
                                        <div class="text-caption text-medium-emphasis">
                                            Остаток {{ formatMoney(a.remaining) }}
                                            · из {{ formatMoney(a.amount) }}
                                        </div>
                                    </div>
                                    <v-chip
                                        size="x-small"
                                        variant="flat"
                                        class="skydesk-pill"
                                        :style="dictChipStyle(a.status_color || '#6957EE')"
                                    >
                                        {{ a.status_label || a.status_id || '—' }}
                                    </v-chip>
                                </div>
                            </v-card>
                        </section>

                        <section>
                            <h2 class="text-subtitle-1 font-weight-bold mb-3">Движение денежных средств</h2>
                            <v-card>
                                <div v-if="!(finance.transactions || []).length" class="text-caption text-medium-emphasis pa-4">
                                    Движений пока нет.
                                </div>
                                <div
                                    v-for="tx in finance.transactions || []"
                                    :key="tx.id"
                                    class="cash-public__tx"
                                    :class="`cash-public__tx--${movementTone(tx)}`"
                                >
                                    <div class="min-w-0 flex-grow-1">
                                        <div class="text-body-2 font-weight-medium">{{ tx.title }}</div>
                                        <div class="text-caption text-medium-emphasis">
                                            {{ tx.occurred_at }} · {{ tx.type }}
                                        </div>
                                    </div>
                                    <div class="cash-public__amount">
                                        {{ movementSign(tx) }}{{ formatMoney(Math.abs(Number(tx.amount || 0))) }}
                                    </div>
                                </div>
                            </v-card>
                        </section>
                    </template>
                </v-container>
            </v-main>
        </div>

        <PinDialog
            v-model="showPin"
            title="Доступ к деньгам"
            subtitle="Введите 4-значный код руководителя"
            confirm-label="Открыть"
            :loading="form.processing"
            :error="pinError"
            @submit="unlock"
            @cancel="closePin"
        />
    </v-app>
</template>

<style scoped>
.cash-public {
    position: relative;
    min-height: 100vh;
    min-height: 100dvh;
    background: rgb(var(--v-theme-background));
}

.cash-public__glow {
    position: fixed;
    border-radius: 50%;
    filter: blur(48px);
    pointer-events: none;
    z-index: 0;
}

.cash-public__glow--a {
    width: 420px;
    height: 420px;
    top: -120px;
    left: -80px;
    background: rgba(105, 87, 238, 0.22);
}

.cash-public__glow--b {
    width: 360px;
    height: 360px;
    top: 80px;
    right: -100px;
    background: rgba(255, 173, 77, 0.16);
}

.cash-public__glow--c {
    width: 300px;
    height: 300px;
    bottom: 10%;
    left: 30%;
    background: rgba(55, 168, 120, 0.12);
}

.cash-public--dark .cash-public__glow--a { background: rgba(105, 87, 238, 0.28); }
.cash-public--dark .cash-public__glow--b { background: rgba(255, 173, 77, 0.14); }
.cash-public--dark .cash-public__glow--c { background: rgba(55, 168, 120, 0.1); }

.cash-public__bar,
.cash-public :deep(.v-main) {
    position: relative;
    z-index: 1;
}

.cash-public__hero {
    padding: 20px;
    border-radius: 20px;
    background:
        radial-gradient(120% 140% at 0% 0%, rgba(105, 87, 238, 0.22), transparent 55%),
        radial-gradient(100% 120% at 100% 0%, rgba(55, 168, 120, 0.14), transparent 50%),
        rgba(var(--v-theme-surface), 0.72);
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.cash-public__eyebrow {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: rgb(var(--v-theme-primary));
    margin-bottom: 6px;
}

.cash-public__title {
    font-family: Fraunces, Georgia, serif;
    font-size: 1.75rem;
    letter-spacing: -0.03em;
    font-weight: 700;
    line-height: 1.15;
}

.cash-public__sum {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.cash-public__total {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 18px 20px;
    border-radius: 18px;
    background:
        radial-gradient(120% 140% at 0% 0%, color-mix(in srgb, var(--tile-color) 22%, transparent), transparent 55%),
        var(--tile-bg);
    border: 1px solid color-mix(in srgb, var(--tile-color) 28%, transparent);
}

.cash-public__total-top {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.cash-public__total-copy {
    min-width: 0;
}

.cash-public__total-hint {
    margin-top: 2px;
    font-size: 0.72rem;
    line-height: 1.25;
    color: rgba(var(--v-theme-on-surface), 0.55);
}

.cash-public__total-value {
    font-family: Fraunces, Georgia, serif;
    font-weight: 700;
    font-size: clamp(1.45rem, 4vw, 1.85rem);
    letter-spacing: -0.03em;
    color: var(--tile-color);
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
    flex-shrink: 0;
}

.cash-public__parts {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.cash-public__op {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 28px;
    flex-shrink: 0;
    font-family: Fraunces, Georgia, serif;
    font-size: 1.15rem;
    font-weight: 700;
    line-height: 1;
    color: rgba(var(--v-theme-on-surface), 0.45);
}

.cash-public__tile {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border-radius: 14px;
    background: var(--tile-bg);
    flex: 1;
    min-width: 0;
}

.cash-public__tile-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    color: var(--tile-color);
    background: color-mix(in srgb, var(--tile-color) 16%, transparent);
    flex-shrink: 0;
}

.cash-public__tile-icon--lg {
    width: 40px;
    height: 40px;
    border-radius: 12px;
}

.cash-public__tile-body {
    flex: 1;
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 12px;
    min-width: 0;
}

.cash-public__tile-label {
    font-size: 0.8rem;
    color: rgba(var(--v-theme-on-surface), 0.65);
}

.cash-public__tile-value {
    font-family: Fraunces, Georgia, serif;
    font-weight: 700;
    font-size: 1.05rem;
    color: var(--tile-color);
    font-variant-numeric: tabular-nums;
}

@media (min-width: 700px) {
    .cash-public__parts {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 28px minmax(0, 1fr) 28px minmax(0, 1fr);
        align-items: stretch;
        gap: 0;
    }

    .cash-public__op {
        height: auto;
        width: 28px;
        font-size: 1.35rem;
    }

    .cash-public__tile {
        flex-direction: column;
        align-items: flex-start;
        min-height: 100px;
    }

    .cash-public__tile-icon {
        width: auto;
        height: auto;
        background: transparent;
        margin-bottom: 8px;
        display: block;
        padding: 0;
    }

    .cash-public__tile-body {
        flex-direction: column;
        align-items: flex-start;
        gap: 2px;
        width: 100%;
    }
}

.cash-public__tx {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.cash-public__tx--in { background: rgba(55, 168, 120, 0.05); }
.cash-public__tx--out { background: rgba(233, 102, 103, 0.05); }

.cash-public__amount {
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.cash-public__tx--in .cash-public__amount { color: #37A878; }
.cash-public__tx--out .cash-public__amount { color: #E96667; }
.cash-public__tx--xfer .cash-public__amount { color: #5B8DEF; }

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
.skydesk-wordmark--mobile { font-size: 1.05rem; }
</style>
