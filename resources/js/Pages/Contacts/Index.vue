<script setup>
import { computed, ref } from 'vue';
import { useDisplay } from 'vuetify';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';
import { useWorkspaceUi } from '@/composables/useWorkspaceUi';

const { mdAndUp } = useDisplay();
const store = useSkyDeskStore();
const { openContact } = useWorkspaceUi();

const query = ref('');

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    const list = [...store.contacts.value].sort((a, b) => a.name.localeCompare(b.name, 'ru'));
    if (!q) return list;
    return list.filter(
        (c) =>
            c.name.toLowerCase().includes(q)
            || (c.role || '').toLowerCase().includes(q)
            || (c.phone || '').toLowerCase().includes(q)
            || (c.note || '').toLowerCase().includes(q),
    );
});

const initials = (name) => {
    const parts = (name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '?';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[1][0]).toUpperCase();
};

const create = async () => {
    const contact = await store.createContact({ name: '' });
    if (contact?.id) openContact(contact.id);
};
</script>

<template>
    <AppLayout
        title="Контакты"
        subtitle="Люди и организации"
        :show-fab="false"
    >
        <template #actions>
            <v-text-field
                v-model="query"
                prepend-inner-icon="mdi-magnify"
                label="Поиск"
                density="compact"
                hide-details
                clearable
                class="flex-grow-1"
                style="min-width:140px;max-width:280px"
            />
            <v-btn color="primary" size="small" prepend-icon="mdi-plus" @click="create">
                Новый контакт
            </v-btn>
        </template>

        <v-card>
            <div
                v-for="contact in filtered"
                :key="contact.id"
                class="skydesk-task d-flex align-center ga-3 px-4 py-3"
                style="cursor:pointer;border-bottom:1px solid rgba(var(--v-border-color),var(--v-border-opacity))"
                @click="openContact(contact.id)"
            >
                <v-avatar color="primary" variant="tonal" size="36">
                    <span class="text-caption font-weight-bold">{{ initials(contact.name) }}</span>
                </v-avatar>
                <div class="flex-grow-1 min-w-0">
                    <div class="text-body-2 font-weight-bold text-truncate">{{ contact.name || 'Без имени' }}</div>
                    <div class="text-caption text-medium-emphasis text-truncate">
                        <template v-if="contact.role">{{ contact.role }}</template>
                        <template v-if="contact.role && contact.phone"> · </template>
                        <template v-if="contact.phone">{{ contact.phone }}</template>
                        <template v-if="!contact.role && !contact.phone">Без деталей</template>
                    </div>
                </div>
                <v-btn
                    v-if="contact.phone"
                    icon
                    variant="text"
                    size="small"
                    :href="`tel:${contact.phone.replace(/\s/g, '')}`"
                    @click.stop
                >
                    <v-icon>mdi-phone-outline</v-icon>
                </v-btn>
            </div>

            <div v-if="!filtered.length" class="pa-8 text-center text-medium-emphasis">
                Контактов пока нет
            </div>
        </v-card>

        <v-btn
            v-if="!mdAndUp"
            color="primary"
            icon
            size="x-large"
            elevation="8"
            class="skydesk-fab"
            style="position:fixed;right:20px;bottom:88px;z-index:21;border-radius:18px !important"
            @click="create"
        >
            <v-icon size="32">mdi-plus</v-icon>
        </v-btn>
    </AppLayout>
</template>
