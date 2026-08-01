<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { useDisplay } from 'vuetify';

const props = defineProps({
    users: { type: Array, default: () => [] },
});

const page = usePage();
const { mdAndUp } = useDisplay();
const authUser = computed(() => page.props.auth?.user);

const createOpen = ref(false);
const editOpen = ref(false);
const editing = ref(null);
const showCreatePassword = ref(false);
const showEditPassword = ref(false);

const createForm = useForm({
    login: '',
    name: '',
    initials: '',
    role: 'Личный помощник',
    password: '',
    is_admin: false,
    telegram_id: '',
});

const editForm = useForm({
    name: '',
    initials: '',
    role: '',
    password: '',
    is_admin: false,
    telegram_id: '',
});

const openCreate = () => {
    createForm.reset();
    createForm.clearErrors();
    createForm.is_admin = false;
    createForm.role = 'Личный помощник';
    createOpen.value = true;
};

const submitCreate = () => {
    createForm
        .transform((data) => ({
            ...data,
            telegram_id: data.telegram_id === '' || data.telegram_id === null ? null : Number(data.telegram_id),
        }))
        .post('/settings/users', {
            preserveScroll: true,
            onSuccess: () => {
                createOpen.value = false;
                createForm.reset();
            },
        });
};

const openEdit = (user) => {
    editing.value = user;
    editForm.clearErrors();
    editForm.name = user.name || '';
    editForm.initials = user.initials || '';
    editForm.role = user.role || '';
    editForm.password = '';
    editForm.is_admin = !!user.is_admin;
    editForm.telegram_id = user.telegram_id ?? '';
    editOpen.value = true;
};

const submitEdit = () => {
    if (!editing.value) return;
    editForm
        .transform((data) => ({
            name: data.name,
            initials: data.initials || null,
            role: data.role || null,
            password: data.password || null,
            is_admin: data.is_admin,
            telegram_id: data.telegram_id === '' || data.telegram_id === null ? null : Number(data.telegram_id),
        }))
        .put(`/settings/users/${editing.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                editOpen.value = false;
                editing.value = null;
                editForm.reset();
            },
        });
};

const loginHint = (email) => {
    if (!email) return '';
    return email.endsWith('@skydesk.local') ? email.replace('@skydesk.local', '') : email;
};
</script>

<template>
    <v-card class="pa-5 mb-4" variant="flat" border>
        <div class="d-flex align-center justify-space-between mb-4 flex-wrap ga-2">
            <div>
                <h2 class="text-subtitle-1 font-weight-bold mb-1">Пользователи</h2>
                <p class="text-body-2 text-medium-emphasis mb-0">
                    Создание аккаунтов, пароль и Telegram ID
                </p>
            </div>
            <v-btn color="primary" prepend-icon="mdi-account-plus" @click="openCreate">
                Новый пользователь
            </v-btn>
        </div>

        <div
            v-for="user in users"
            :key="user.id"
            class="d-flex align-center ga-3 px-3 py-3 mb-2"
            style="border-radius:11px;border:1px solid rgba(var(--v-border-color),var(--v-border-opacity));cursor:pointer"
            @click="openEdit(user)"
        >
            <v-avatar size="36" color="primary">
                <span class="text-caption font-weight-bold">{{ user.initials }}</span>
            </v-avatar>
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex align-center ga-2 flex-wrap">
                    <div class="text-body-2 font-weight-bold text-truncate">{{ user.name }}</div>
                    <v-chip v-if="user.is_admin" size="x-small" color="primary" variant="tonal">admin</v-chip>
                    <v-chip v-if="user.id === authUser?.id" size="x-small" variant="tonal">вы</v-chip>
                </div>
                <div class="text-caption text-medium-emphasis text-truncate">
                    {{ loginHint(user.email) }}
                    <template v-if="user.telegram_id"> · TG {{ user.telegram_id }}</template>
                    <template v-else> · Telegram не привязан</template>
                </div>
            </div>
            <v-icon size="18">mdi-chevron-right</v-icon>
        </div>

        <v-dialog
            v-model="createOpen"
            :fullscreen="!mdAndUp"
            :max-width="mdAndUp ? 480 : undefined"
        >
            <v-card class="pa-5">
                <div class="text-h6 font-weight-bold mb-4">Новый пользователь</div>
                <v-text-field
                    v-model="createForm.login"
                    label="Логин"
                    hint="nkorsakov или email@domain"
                    persistent-hint
                    class="mb-2"
                    :error-messages="createForm.errors.login"
                />
                <v-text-field
                    v-model="createForm.name"
                    label="Имя"
                    class="mb-1"
                    :error-messages="createForm.errors.name"
                />
                <v-text-field v-model="createForm.initials" label="Инициалы" class="mb-1" />
                <v-text-field v-model="createForm.role" label="Роль" class="mb-1" />
                <v-text-field
                    v-model="createForm.password"
                    label="Пароль"
                    :type="showCreatePassword ? 'text' : 'password'"
                    class="mb-1"
                    :error-messages="createForm.errors.password"
                    :append-inner-icon="showCreatePassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
                    @click:append-inner="showCreatePassword = !showCreatePassword"
                />
                <v-text-field
                    v-model="createForm.telegram_id"
                    label="Telegram chat / user ID"
                    type="number"
                    class="mb-2"
                    :error-messages="createForm.errors.telegram_id"
                />
                <v-switch
                    v-model="createForm.is_admin"
                    label="Администратор"
                    color="primary"
                    hide-details
                    class="mb-4"
                />
                <div class="d-flex justify-end ga-2">
                    <v-btn variant="tonal" @click="createOpen = false">Отмена</v-btn>
                    <v-btn color="primary" :loading="createForm.processing" @click="submitCreate">
                        Создать
                    </v-btn>
                </div>
            </v-card>
        </v-dialog>

        <v-dialog
            v-model="editOpen"
            :fullscreen="!mdAndUp"
            :max-width="mdAndUp ? 480 : undefined"
        >
            <v-card v-if="editing" class="pa-5">
                <div class="text-h6 font-weight-bold mb-1">{{ editing.name }}</div>
                <div class="text-caption text-medium-emphasis mb-4">{{ editing.email }}</div>
                <v-text-field
                    v-model="editForm.name"
                    label="Имя"
                    class="mb-1"
                    :error-messages="editForm.errors.name"
                />
                <v-text-field v-model="editForm.initials" label="Инициалы" class="mb-1" />
                <v-text-field v-model="editForm.role" label="Роль" class="mb-1" />
                <v-text-field
                    v-model="editForm.password"
                    label="Новый пароль"
                    hint="Оставьте пустым, чтобы не менять"
                    persistent-hint
                    :type="showEditPassword ? 'text' : 'password'"
                    class="mb-2"
                    :error-messages="editForm.errors.password"
                    :append-inner-icon="showEditPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
                    @click:append-inner="showEditPassword = !showEditPassword"
                />
                <v-text-field
                    v-model="editForm.telegram_id"
                    label="Telegram chat / user ID"
                    type="number"
                    class="mb-2"
                    :error-messages="editForm.errors.telegram_id"
                />
                <v-switch
                    v-model="editForm.is_admin"
                    label="Администратор"
                    color="primary"
                    hide-details
                    class="mb-2"
                    :error-messages="editForm.errors.is_admin"
                />
                <div class="d-flex justify-end ga-2 mt-4">
                    <v-btn variant="tonal" @click="editOpen = false">Отмена</v-btn>
                    <v-btn color="primary" :loading="editForm.processing" @click="submitEdit">
                        Сохранить
                    </v-btn>
                </div>
            </v-card>
        </v-dialog>
    </v-card>
</template>
