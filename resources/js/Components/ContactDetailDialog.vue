<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { useDisplay } from 'vuetify';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';
import { linkifyParts } from '@/utils/linkify';

const model = defineModel({ type: Boolean, default: false });
const props = defineProps({
    contactId: { type: [String, Number], default: null },
});

const { mdAndUp } = useDisplay();
const store = useSkyDeskStore();
const confirmDelete = ref(false);
const nameInput = ref(null);
const noteInput = ref(null);
const editingNote = ref(false);

const contact = computed(() => (props.contactId ? store.getContact(props.contactId) : null));

const form = reactive({
    name: '',
    role: '',
    phone: '',
    note: '',
});

const isDraftName = (name) => {
    const n = String(name || '').trim();
    return !n || n === 'Новый контакт';
};

const noteParts = computed(() => linkifyParts(form.note));

watch(
    () => [model.value, props.contactId],
    async () => {
        if (!model.value || !contact.value) return;
        const draft = isDraftName(contact.value.name);
        form.name = draft ? '' : contact.value.name;
        form.role = contact.value.role || '';
        form.phone = contact.value.phone || '';
        form.note = contact.value.note || '';
        confirmDelete.value = false;
        editingNote.value = false;
        if (draft) {
            await nextTick();
            const el = nameInput.value?.$el?.querySelector?.('input');
            el?.focus?.();
        }
    },
    { immediate: true },
);

watch(
    form,
    () => {
        if (!model.value || !props.contactId) return;
        store.updateContact(props.contactId, {
            name: form.name.trim(),
            role: form.role.trim(),
            phone: form.phone.trim(),
            note: form.note.trim(),
        });
    },
    { deep: true },
);

const startEditNote = async () => {
    editingNote.value = true;
    await nextTick();
    const el = noteInput.value?.$el?.querySelector?.('textarea');
    el?.focus?.();
};

const stopEditNote = () => {
    editingNote.value = false;
};

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
                <v-text-field
                    ref="nameInput"
                    v-model="form.name"
                    label="Имя"
                    placeholder="Как зовут"
                    class="mb-1"
                />
                <v-text-field v-model="form.role" label="Роль / кем приходится" class="mb-1" />
                <v-text-field v-model="form.phone" label="Телефон" type="tel" class="mb-1" />

                <div v-if="!editingNote" class="skydesk-note-read mb-2" @click="startEditNote">
                    <div class="text-caption text-medium-emphasis mb-1">Заметка</div>
                    <div
                        v-if="form.note.trim()"
                        class="text-body-2"
                        style="white-space:pre-wrap;word-break:break-word;line-height:1.45"
                    >
                        <template v-for="(part, idx) in noteParts" :key="idx">
                            <a
                                v-if="part.type === 'link'"
                                :href="part.href"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="skydesk-note-link"
                                @click.stop
                            >{{ part.value }}</a>
                            <template v-else>{{ part.value }}</template>
                        </template>
                    </div>
                    <div v-else class="text-body-2 text-medium-emphasis">Нажмите, чтобы добавить заметку</div>
                </div>
                <v-textarea
                    v-else
                    ref="noteInput"
                    v-model="form.note"
                    label="Заметка"
                    rows="3"
                    auto-grow
                    max-rows="8"
                    hide-details
                    class="mb-2"
                    autofocus
                    @blur="stopEditNote"
                />

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
            <p class="text-body-2 text-medium-emphasis mb-4">{{ form.name || 'Без имени' }}</p>
            <div class="d-flex justify-end ga-2">
                <v-btn variant="tonal" @click="confirmDelete = false">Отмена</v-btn>
                <v-btn color="error" @click="remove">Удалить</v-btn>
            </div>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.skydesk-note-read {
    cursor: text;
    border-radius: 12px;
    padding: 12px 14px;
    border: 1px solid transparent;
    transition: background-color 160ms ease, border-color 160ms ease;
    min-height: 84px;
}

.skydesk-note-read:hover {
    background: rgba(var(--v-theme-on-surface), 0.04);
    border-color: rgba(var(--v-border-color), var(--v-border-opacity));
}

.skydesk-note-link {
    color: rgb(var(--v-theme-primary));
    text-decoration: underline;
    text-underline-offset: 2px;
    word-break: break-all;
}

.skydesk-note-link:hover {
    opacity: 0.85;
}
</style>
