<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DictionaryEditor from '@/Components/DictionaryEditor.vue';
import AppearanceMenu from '@/Components/AppearanceMenu.vue';
import UsersAdminPanel from '@/Components/UsersAdminPanel.vue';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';

const props = defineProps({
    users: { type: Array, default: () => [] },
});

const page = usePage();
const store = useSkyDeskStore();
const authUser = computed(() => page.props.auth?.user);
const flashStatus = computed(() => page.props.flash?.status);
const isAdmin = computed(() => !!authUser.value?.is_admin);

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
const dictTab = ref('statuses');
const supplierDraft = reactive({
    name: '',
    contact_id: null,
});

const dictTabs = [
    { value: 'statuses', label: 'Статусы', dictKey: 'statuses' },
    { value: 'priorities', label: 'Приоритеты', dictKey: 'priorities' },
    { value: 'taskTypes', label: 'Типы поручений', dictKey: 'taskTypes', withIcon: true },
    { value: 'eventTypes', label: 'Типы событий', dictKey: 'eventTypes' },
    { value: 'advanceStatuses', label: 'Статусы авансов', dictKey: 'advanceStatuses' },
    { value: 'expenseArticles', label: 'Статьи расходов', dictKey: 'expenseArticles' },
    { value: 'disbursementMethods', label: 'Способы выдачи', dictKey: 'disbursementMethods' },
    { value: 'suppliers', label: 'Поставщики' },
];

const dictionaryTabs = dictTabs.filter((t) => t.dictKey);

const linkableContacts = computed(() => {
    const linked = new Set(
        store.suppliers.value
            .map((s) => s.contact_id)
            .filter(Boolean)
            .map(String),
    );
    return store.contacts.value.filter(
        (c) => String(c.name || '').trim() && !linked.has(String(c.id)),
    );
});

const onSupplierContactPick = (contactId) => {
    if (!contactId) return;
    const contact = store.getContact(contactId);
    if (contact && !String(supplierDraft.name || '').trim()) {
        supplierDraft.name = contact.name;
    }
};

const createSupplier = async () => {
    const name = String(supplierDraft.name || '').trim();
    if (!name) return;
    await store.createSupplier({
        name,
        contact_id: supplierDraft.contact_id || null,
    });
    supplierDraft.name = '';
    supplierDraft.contact_id = null;
};

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

                <UsersAdminPanel v-if="isAdmin" :users="props.users" />
            </v-col>

            <v-col cols="12" md="7">
                <v-card variant="flat" border>
                    <v-tabs
                        v-model="dictTab"
                        color="primary"
                        density="comfortable"
                        show-arrows
                        class="px-2"
                    >
                        <v-tab
                            v-for="tab in dictTabs"
                            :key="tab.value"
                            :value="tab.value"
                        >
                            {{ tab.label }}
                        </v-tab>
                    </v-tabs>

                    <v-tabs-window v-model="dictTab">
                        <v-tabs-window-item
                            v-for="tab in dictionaryTabs"
                            :key="tab.value"
                            :value="tab.value"
                        >
                            <DictionaryEditor
                                :dict-key="tab.dictKey"
                                embedded
                                :with-icon="!!tab.withIcon"
                            />
                        </v-tabs-window-item>

                        <v-tabs-window-item value="suppliers">
                            <div class="pa-5">
                                <p class="text-body-2 text-medium-emphasis mb-3">
                                    Поставщика можно создать отдельно или привязать к контакту.
                                </p>
                                <v-text-field
                                    v-model="supplierDraft.name"
                                    label="Название"
                                    class="mb-2"
                                    hide-details="auto"
                                    @keyup.enter="createSupplier"
                                />
                                <v-select
                                    v-model="supplierDraft.contact_id"
                                    :items="linkableContacts"
                                    item-title="name"
                                    item-value="id"
                                    label="Контакт (необязательно)"
                                    clearable
                                    class="mb-3"
                                    hide-details="auto"
                                    @update:model-value="onSupplierContactPick"
                                />
                                <v-btn
                                    color="primary"
                                    size="small"
                                    class="mb-4"
                                    :disabled="!String(supplierDraft.name || '').trim()"
                                    @click="createSupplier"
                                >
                                    Добавить поставщика
                                </v-btn>
                                <div
                                    v-for="s in store.suppliers.value"
                                    :key="s.id"
                                    class="d-flex align-center justify-space-between py-2"
                                    style="border-bottom:1px solid rgba(var(--v-border-color),var(--v-border-opacity))"
                                >
                                    <div>
                                        <div class="font-weight-bold">{{ s.name || 'Без имени' }}</div>
                                        <div class="text-caption text-medium-emphasis">
                                            <template v-if="s.contact_id">
                                                Контакт: {{ s.contact_name || store.getContact(s.contact_id)?.name || '—' }}
                                            </template>
                                            <template v-else>Без контакта</template>
                                        </div>
                                    </div>
                                    <v-btn size="small" variant="tonal" color="error" @click="store.removeSupplier(s.id)">
                                        Удалить
                                    </v-btn>
                                </div>
                                <div v-if="!store.suppliers.value.length" class="text-caption text-medium-emphasis">
                                    Пока нет поставщиков.
                                </div>
                            </div>
                        </v-tabs-window-item>
                    </v-tabs-window>
                </v-card>
            </v-col>
        </v-row>
    </AppLayout>
</template>
