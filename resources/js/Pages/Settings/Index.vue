<script setup>
import { reactive } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DictionaryEditor from '@/Components/DictionaryEditor.vue';
import AppearanceMenu from '@/Components/AppearanceMenu.vue';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';

const store = useSkyDeskStore();

const profileForm = reactive({
    name: store.profile.value.name,
    initials: store.profile.value.initials,
    role: store.profile.value.role,
});

const saveProfile = () => {
    store.updateProfile({ ...profileForm });
};

const resetData = () => {
    if (confirm('Сбросить все демо-данные к исходным?')) {
        store.resetStore();
        profileForm.name = store.profile.value.name;
        profileForm.initials = store.profile.value.initials;
        profileForm.role = store.profile.value.role;
    }
};
</script>

<template>
    <AppLayout
        title="Настройки"
        subtitle="Профиль, словари и оформление."
        :show-fab="false"
    >
        <v-row>
            <v-col cols="12" md="5">
                <v-card class="pa-5 mb-4">
                    <div class="d-flex align-center justify-space-between mb-4">
                        <h2 class="text-subtitle-1 font-weight-bold mb-0">Профиль</h2>
                        <AppearanceMenu />
                    </div>
                    <v-text-field v-model="profileForm.name" label="Имя" class="mb-1" />
                    <v-text-field v-model="profileForm.initials" label="Инициалы" class="mb-1" />
                    <v-text-field v-model="profileForm.role" label="Роль" class="mb-3" />
                    <v-btn color="primary" @click="saveProfile">Сохранить профиль</v-btn>
                </v-card>

                <v-card class="pa-5">
                    <h2 class="text-subtitle-1 font-weight-bold mb-2">Демо-данные</h2>
                    <p class="text-body-2 text-medium-emphasis mb-4">
                        Прототип хранит изменения в браузере (localStorage).
                    </p>
                    <v-btn variant="tonal" color="error" @click="resetData">Сбросить моки</v-btn>
                </v-card>
            </v-col>

            <v-col cols="12" md="7">
                <DictionaryEditor dict-key="statuses" title="Статусы поручений" />
                <DictionaryEditor dict-key="priorities" title="Приоритеты" />
                <DictionaryEditor dict-key="taskTypes" title="Типы поручений" with-icon />
                <DictionaryEditor dict-key="eventTypes" title="Типы событий" />
            </v-col>
        </v-row>
    </AppLayout>
</template>
