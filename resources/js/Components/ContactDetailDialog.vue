<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useDisplay } from 'vuetify';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';

const model = defineModel({ type: Boolean, default: false });
const props = defineProps({
    contactId: { type: [String, Number], default: null },
});

const { mdAndUp } = useDisplay();
const store = useSkyDeskStore();
const confirmDelete = ref(false);

const contact = computed(() => (props.contactId ? store.getContact(props.contactId) : null));

const form = reactive({
    name: '',
    role: '',
    phone: '',
    note: '',
});

watch(
    () => [model.value, props.contactId],
    () => {
        if (!model.value || !contact.value) return;
        form.name = contact.value.name;
        form.role = contact.value.role || '';
        form.phone = contact.value.phone || '';
        form.note = contact.value.note || '';
        confirmDelete.value = false;
    },
    { immediate: true },
);

watch(
    form,
    () => {
        if (!model.value || !props.contactId) return;
        store.updateContact(props.contactId, {
            name: form.name.trim() || contact.value.name,
            role: form.role.trim(),
            phone: form.phone.trim(),
            note: form.note.trim(),
        });
    },
    { deep: true },
);

const remove = () => {
    store.removeContact(props.contactId);
    confirmDelete.value = false;
    model.value = false;
};

const initials = computed(() => {
    const parts = (form.name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '?';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[1][0]).toUpperCase();
});
</script>

<template>
    <v-dialog
        v-model="model"
        :fullscreen="!mdAndUp"
        :max-width="mdAndUp ? 520 : undefined"
        scrollable
    >
        <v-card v-if="contact" class="d-flex flex-column" :style="mdAndUp ? 'max-height:90vh' : 'min-height:100%'">
            <v-card-title class="d-flex align-center justify-space-between px-6 pt-5">
                <div class="d-flex align-center ga-3">
                    <v-avatar color="primary" size="40">
                        <span class="text-caption font-weight-bold">{{ initials }}</span>
                    </v-avatar>
                    <span class="text-h6 font-weight-bold">Контакт</span>
                </div>
                <v-btn icon variant="tonal" size="small" @click="model = false">
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </v-card-title>
            <v-divider />
            <v-card-text class="px-6 py-5">
                <v-text-field v-model="form.name" label="Имя" class="mb-1" />
                <v-text-field v-model="form.role" label="Роль / кем приходится" class="mb-1" />
                <v-text-field v-model="form.phone" label="Телефон" type="tel" class="mb-1" />
                <v-textarea v-model="form.note" label="Заметка" rows="3" auto-grow />

                <v-btn
                    class="mt-2"
                    variant="tonal"
                    color="error"
                    prepend-icon="mdi-delete-outline"
                    @click="confirmDelete = true"
                >
                    Удалить
                </v-btn>
            </v-card-text>
        </v-card>
    </v-dialog>

    <v-dialog v-model="confirmDelete" max-width="400">
        <v-card class="pa-5">
            <div class="text-h6 font-weight-bold mb-2">Удалить контакт?</div>
            <p class="text-body-2 text-medium-emphasis mb-4">{{ form.name }}</p>
            <div class="d-flex justify-end ga-2">
                <v-btn variant="tonal" @click="confirmDelete = false">Отмена</v-btn>
                <v-btn color="error" @click="remove">Удалить</v-btn>
            </div>
        </v-card>
    </v-dialog>
</template>
