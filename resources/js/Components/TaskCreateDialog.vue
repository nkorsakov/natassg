<script setup>
import { computed, ref, watch } from 'vue';
import { useDisplay } from 'vuetify';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';

const model = defineModel({ type: Boolean, default: false });
const props = defineProps({
    eventId: { type: String, default: null },
    parentId: { type: String, default: null },
});
const emit = defineEmits(['created']);

const { mdAndUp } = useDisplay();
const store = useSkyDeskStore();
const title = ref('');

watch(model, (open) => {
    if (open) title.value = '';
});

const canSave = computed(() => title.value.trim().length > 0);

const save = () => {
    if (!canSave.value) return;
    const task = store.createTask({
        title: title.value.trim(),
        parent_id: props.parentId,
        event_ids: props.eventId ? [props.eventId] : [],
        status_id: 'new',
    });
    if (props.eventId) store.linkTaskEvent(task.id, props.eventId);
    model.value = false;
    emit('created', task.id);
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
                <v-text-field
                    v-model="title"
                    label="Что нужно сделать?"
                    placeholder="Например, купить цветы к ужину"
                    autofocus
                    @keyup.enter="save"
                />
            </v-card-text>
            <v-divider />
            <v-card-actions class="px-6 py-4">
                <v-spacer />
                <v-btn variant="tonal" @click="model = false">Отмена</v-btn>
                <v-btn color="primary" :disabled="!canSave" @click="save">Создать</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
