<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DictionaryEditor from '@/Components/DictionaryEditor.vue';
import AppearanceMenu from '@/Components/AppearanceMenu.vue';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';

const page = usePage();
const store = useSkyDeskStore();
const authUser = computed(() => page.props.auth?.user);
const flashStatus = computed(() => page.props.flash?.status);

const profileForm = reactive({
    name: store.profile.value.name,
    initials: store.profile.value.initials,
    role: store.profile.value.role,
});

watch(
    authUser,
    (user) => {
        if (!user) return;
        profileForm.name = user.name;
        profileForm.initials = user.initials;
        profileForm.role = user.role || store.profile.value.role;
    },
    { immediate: true },
);

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const showCurrent = ref(false);
const showNew = ref(false);
const showConfirm = ref(false);

const saveProfile = () => {
    store.updateProfile({ ...profileForm });
};

const updatePassword = () => {
    passwordForm.put('/settings/password', {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
};

const logout = () => {
    router.post('/logout');
};
</script>

<template>
    <AppLayout
        title="Настройки"
        subtitle="Профиль, пароль, словари и оформление."
        :show-fab="false"
    >
        <v-row>
            <v-col cols="12" md="5">
                <v-card class="pa-5 mb-4" variant="flat" border>
                    <div class="d-flex align-center justify-space-between mb-4">
                        <h2 class="text-subtitle-1 font-weight-bold mb-0">Профиль</h2>
                        <AppearanceMenu />
                    </div>
                    <v-text-field
                        :model-value="authUser?.email"
                        label="Email"
                        readonly
                        class="mb-1"
                        hide-details="auto"
                    />
                    <v-text-field v-model="profileForm.name" label="Имя" class="mb-1" />
                    <v-text-field v-model="profileForm.initials" label="Инициалы" class="mb-1" />
                    <v-text-field v-model="profileForm.role" label="Роль" class="mb-3" />
                    <div class="d-flex flex-wrap ga-2">
                        <v-btn color="primary" @click="saveProfile">Сохранить профиль</v-btn>
                        <v-btn variant="tonal" color="error" @click="logout">Выйти</v-btn>
                    </div>
                </v-card>

                <v-card class="pa-5 mb-4" variant="flat" border>
                    <h2 class="text-subtitle-1 font-weight-bold mb-2">Сменить пароль</h2>
                    <v-alert
                        v-if="flashStatus"
                        type="success"
                        variant="tonal"
                        class="mb-3"
                        density="comfortable"
                    >
                        {{ flashStatus }}
                    </v-alert>
                    <v-form @submit.prevent="updatePassword">
                        <v-text-field
                            v-model="passwordForm.current_password"
                            label="Текущий пароль"
                            :type="showCurrent ? 'text' : 'password'"
                            autocomplete="current-password"
                            class="mb-1"
                            :error-messages="passwordForm.errors.current_password"
                            :append-inner-icon="showCurrent ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
                            @click:append-inner="showCurrent = !showCurrent"
                        />
                        <v-text-field
                            v-model="passwordForm.password"
                            label="Новый пароль"
                            :type="showNew ? 'text' : 'password'"
                            autocomplete="new-password"
                            class="mb-1"
                            :error-messages="passwordForm.errors.password"
                            :append-inner-icon="showNew ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
                            @click:append-inner="showNew = !showNew"
                        />
                        <v-text-field
                            v-model="passwordForm.password_confirmation"
                            label="Повтор нового пароля"
                            :type="showConfirm ? 'text' : 'password'"
                            autocomplete="new-password"
                            class="mb-3"
                            :append-inner-icon="showConfirm ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
                            @click:append-inner="showConfirm = !showConfirm"
                        />
                        <v-btn
                            type="submit"
                            color="primary"
                            :loading="passwordForm.processing"
                            :disabled="passwordForm.processing"
                        >
                            Обновить пароль
                        </v-btn>
                    </v-form>
                </v-card>

                <v-card class="pa-5" variant="flat" border>
                    <h2 class="text-subtitle-2 font-weight-bold mb-2">Данные</h2>
                    <p class="text-body-2 text-medium-emphasis mb-0">
                        Поручения, финансы и контакты хранятся на сервере. Демо-наполнение не используется — экраны стартуют пустыми.
                    </p>
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
