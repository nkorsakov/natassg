<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useDisplay } from 'vuetify';

const open = defineModel({ type: Boolean, default: false });

const props = defineProps({
    title: { type: String, default: 'Введите код' },
    subtitle: { type: String, default: '' },
    length: { type: Number, default: 4 },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Подтвердить' },
});

const emit = defineEmits(['submit', 'cancel']);

const { mdAndUp } = useDisplay();
const digits = ref([]);
const inputRefs = ref([]);

const resetDigits = () => {
    digits.value = Array.from({ length: props.length }, () => '');
};

resetDigits();

watch(open, async (v) => {
    if (!v) return;
    resetDigits();
    await nextTick();
    focusAt(0);
});

watch(
    () => props.error,
    async (msg) => {
        if (!msg) return;
        resetDigits();
        await nextTick();
        focusAt(0);
    },
);

const pin = computed(() => digits.value.join(''));
const canSubmit = computed(() => pin.value.length === props.length && !props.loading);

const setRef = (el, idx) => {
    if (el) inputRefs.value[idx] = el;
};

const focusAt = (idx) => {
    const el = inputRefs.value[idx];
    if (!el) return;
    el.focus?.();
    el.select?.();
};

const onInput = (idx, value) => {
    const cleaned = String(value || '').replace(/\D/g, '');
    const next = [...digits.value];

    if (!cleaned) {
        next[idx] = '';
        digits.value = next;
        return;
    }

    const chars = cleaned.slice(0, props.length - idx).split('');
    chars.forEach((ch, offset) => {
        next[idx + offset] = ch;
    });
    digits.value = next;

    const focusIdx = Math.min(idx + chars.length, props.length - 1);
    nextTick(() => focusAt(focusIdx));

    if (next.every((d) => d !== '')) {
        nextTick(() => emit('submit', next.join('')));
    }
};

const onKeydown = (idx, e) => {
    if (e.key === 'Backspace') {
        const next = [...digits.value];
        if (next[idx]) {
            next[idx] = '';
            digits.value = next;
            return;
        }
        if (idx > 0) {
            next[idx - 1] = '';
            digits.value = next;
            nextTick(() => focusAt(idx - 1));
        }
        return;
    }
    if (e.key === 'ArrowLeft' && idx > 0) {
        e.preventDefault();
        focusAt(idx - 1);
    }
    if (e.key === 'ArrowRight' && idx < props.length - 1) {
        e.preventDefault();
        focusAt(idx + 1);
    }
    if (e.key === 'Enter' && canSubmit.value) {
        emit('submit', pin.value);
    }
};

const close = () => {
    open.value = false;
    emit('cancel');
    resetDigits();
};

const confirm = () => {
    if (!canSubmit.value) return;
    emit('submit', pin.value);
};
</script>

<template>
    <v-dialog
        v-model="open"
        :max-width="mdAndUp ? 420 : undefined"
        :fullscreen="!mdAndUp"
        :transition="mdAndUp ? 'dialog-transition' : 'dialog-bottom-transition'"
        persistent
    >
        <v-card class="pin-dialog">
            <v-card-title class="d-flex align-center justify-space-between px-6 pt-5">
                <span class="text-h6 font-weight-bold">{{ title }}</span>
                <v-btn icon variant="tonal" size="small" :disabled="loading" @click="close">
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </v-card-title>
            <v-card-text class="px-6 pb-2">
                <p v-if="subtitle" class="text-body-2 text-medium-emphasis mb-5">
                    {{ subtitle }}
                </p>

                <div class="pin-dialog__cells" role="group" aria-label="Код доступа">
                    <input
                        v-for="(_, idx) in digits"
                        :key="idx"
                        :ref="(el) => setRef(el, idx)"
                        class="pin-dialog__cell"
                        :class="{ 'pin-dialog__cell--filled': !!digits[idx], 'pin-dialog__cell--error': !!error }"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        autocomplete="one-time-code"
                        maxlength="1"
                        :value="digits[idx]"
                        :disabled="loading"
                        @input="onInput(idx, $event.target.value)"
                        @keydown="onKeydown(idx, $event)"
                    >
                </div>

                <div v-if="error" class="text-error text-caption text-center mt-3">
                    {{ error }}
                </div>
            </v-card-text>
            <v-card-actions class="px-6 py-4">
                <v-spacer />
                <v-btn variant="tonal" :disabled="loading" @click="close">Отмена</v-btn>
                <v-btn
                    color="primary"
                    :loading="loading"
                    :disabled="!canSubmit"
                    @click="confirm"
                >
                    {{ confirmLabel }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.pin-dialog__cells {
    display: flex;
    justify-content: center;
    gap: 12px;
}

.pin-dialog__cell {
    width: 56px;
    height: 64px;
    border-radius: 16px;
    border: 1.5px solid rgba(var(--v-border-color), var(--v-border-opacity));
    background: rgba(var(--v-theme-surface), 1);
    text-align: center;
    font-family: Fraunces, Georgia, serif;
    font-size: 1.6rem;
    font-weight: 700;
    letter-spacing: 0;
    color: rgb(var(--v-theme-on-surface));
    outline: none;
    transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
}

.pin-dialog__cell:focus {
    border-color: rgb(var(--v-theme-primary));
    box-shadow: 0 0 0 4px rgba(var(--v-theme-primary), 0.16);
}

.pin-dialog__cell--filled {
    background: rgba(var(--v-theme-primary), 0.08);
    border-color: rgba(var(--v-theme-primary), 0.45);
}

.pin-dialog__cell--error {
    border-color: rgb(var(--v-theme-error));
}

.pin-dialog__cell:disabled {
    opacity: 0.6;
}
</style>
