<script setup>
import { computed, ref, watch } from 'vue';
import { useDisplay } from 'vuetify';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';

const model = defineModel({ type: Boolean, default: false });
const props = defineProps({
    eventId: { type: [String, Number], default: null },
    parentId: { type: [String, Number], default: null },
});
const emit = defineEmits(['created']);

const { mdAndUp } = useDisplay();
const store = useSkyDeskStore();
const title = ref('');
const saving = ref(false);

watch(model, (open) => {
    if (open) title.value = '';
});

const canSave = computed(() => title.value.trim().length > 0 && !saving.value);

const save = async () => {
    if (!canSave.value) return;
    saving.value = true;
    try {
        const task = await store.createTask({
            title: title.value.trim(),
            parent_id: props.parentId,
            event_id: props.eventId || null,
            status_id: 'new',
        });
        model.value = false;
        emit('created', task?.id);
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <v-dialog
        v-model="model"
        :fullscreen="!mdAndUp"
        :max-width="mdAndUp ? 480 : undefined"
        :transition="mdAndUp ? 'dialog-transition' : 'dialog-bottom-transition'"
    >
        <v-card>
            <v-card-title class="d-flex align-center justify-space-between px-6 pt-5">
                <span class="text-h6 font-weight-bold">Новое поручение</span>
                <v-btn icon variant="tonal" size="small" @click="model = false">
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </v-card-title>
            <v-divider />
            <v-card-text class="px-6 py-5">
                <p class="text-body-2 text-medium-emphasis mb-3">
                    Достаточно названия — детали можно добавить в карточке.
                </p>
                <v-textarea
                    v-model="title"
                    label="Что нужно сделать?"
                    placeholder="Например, купить цветы к ужину"
                    rows="2"
                    auto-grow
                    max-rows="5"
                    autofocus
                    hide-details="auto"
                    @keydown.enter.exact.prevent="save"
                />
            </v-card-text>
            <v-divider />
            <v-card-actions class="px-6 py-4">
                <v-spacer />
                <v-btn variant="tonal" @click="model = false">Отмена</v-btn>
                <v-btn color="primary" :disabled="!canSave" :loading="saving" @click="save">Создать</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
