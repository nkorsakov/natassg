<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useDisplay } from 'vuetify';

const model = defineModel({ type: Boolean, default: false });
const emit = defineEmits(['created']);

const { mdAndUp } = useDisplay();
const form = reactive({
    title: '',
    type: 'purchase',
    date: new Date().toISOString().slice(0, 10),
    priority: 'normal',
    note: '',
});

const types = [
    { value: 'purchase', label: 'Покупка', icon: 'mdi-shopping-outline' },
    { value: 'search', label: 'Поиск', icon: 'mdi-magnify' },
    { value: 'organize', label: 'Организация', icon: 'mdi-calendar-check' },
    { value: 'call', label: 'Звонок', icon: 'mdi-phone-outline' },
];

const priorities = [
    { value: 'normal', title: 'Обычный' },
    { value: 'high', title: 'Высокий' },
    { value: 'urgent', title: 'Срочный' },
];

watch(model, (open) => {
    if (open) {
        form.title = '';
        form.type = 'purchase';
        form.date = new Date().toISOString().slice(0, 10);
        form.priority = 'normal';
        form.note = '';
    }
});

const canSave = computed(() => form.title.trim().length > 0);

const save = () => {
    if (!canSave.value) return;
    emit('created', { ...form, title: form.title.trim() });
    model.value = false;
};
</script>

<template>
    <v-dialog
        v-model="model"
        :fullscreen="!mdAndUp"
        :max-width="mdAndUp ? 550 : undefined"
        :transition="mdAndUp ? 'dialog-transition' : 'dialog-bottom-transition'"
    >
        <v-card :rounded="mdAndUp ? 'xl' : 0" class="d-flex flex-column" :style="!mdAndUp ? 'min-height:100%' : ''">
            <div v-if="!mdAndUp" class="d-flex justify-center pt-3">
                <div style="width:38px;height:4px;border-radius:10px;background:#d7d6df" />
            </div>

            <v-card-title class="d-flex align-center justify-space-between px-6 pt-5">
                <span class="text-h6 font-weight-bold">Новое поручение</span>
                <v-btn icon variant="tonal" size="small" @click="model = false">
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </v-card-title>

            <v-divider />

            <v-card-text class="px-6 py-5 flex-grow-1">
                <v-text-field
                    v-model="form.title"
                    label="Что нужно сделать?"
                    placeholder="Например, купить цветы к ужину"
                    autofocus
                    class="mb-2"
                />

                <div class="text-caption font-weight-bold mb-2" style="color:#5e606b">Тип поручения</div>
                <v-row dense class="mb-4">
                    <v-col v-for="t in types" :key="t.value" cols="6" sm="3">
                        <v-btn
                            block
                            height="48"
                            :variant="form.type === t.value ? 'tonal' : 'outlined'"
                            :color="form.type === t.value ? 'primary' : undefined"
                            @click="form.type = t.value"
                        >
                            <v-icon start size="18">{{ t.icon }}</v-icon>
                            {{ t.label }}
                        </v-btn>
                    </v-col>
                </v-row>

                <v-row dense>
                    <v-col cols="12" sm="6">
                        <v-text-field v-model="form.date" type="date" label="Когда" />
                    </v-col>
                    <v-col cols="12" sm="6">
                        <v-select v-model="form.priority" :items="priorities" item-title="title" item-value="value" label="Приоритет" />
                    </v-col>
                </v-row>

                <v-textarea
                    v-model="form.note"
                    label="Комментарий (необязательно)"
                    placeholder="Детали, ссылки, пожелания..."
                    rows="3"
                    auto-grow
                />
            </v-card-text>

            <v-divider />

            <v-card-actions class="px-6 py-4">
                <v-spacer v-if="mdAndUp" />
                <v-btn variant="tonal" :block="!mdAndUp" @click="model = false">Отмена</v-btn>
                <v-btn color="primary" :block="!mdAndUp" :disabled="!canSave" @click="save">
                    Создать поручение
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
