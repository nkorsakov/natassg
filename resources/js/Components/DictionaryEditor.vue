<script setup>
import { computed, ref } from 'vue';
import { useDisplay } from 'vuetify';
import { useSkyDeskStore } from '@/composables/useSkyDeskStore';

const props = defineProps({
    dictKey: { type: String, required: true },
    title: { type: String, default: '' },
    withIcon: { type: Boolean, default: false },
    /** Без внешней карточки и заголовка — для вкладок */
    embedded: { type: Boolean, default: false },
});

const { mdAndUp } = useDisplay();
const store = useSkyDeskStore();

const items = computed(() => store.dictionaries.value[props.dictKey] || []);

const iconOptions = [
    'mdi-shopping-outline',
    'mdi-magnify',
    'mdi-calendar-check',
    'mdi-phone-outline',
    'mdi-car',
    'mdi-gift-outline',
    'mdi-home-outline',
    'mdi-briefcase-outline',
];

const editing = ref(null);
const draft = ref({ label: '', color: '#6957EE', icon: 'mdi-checkbox-blank-circle-outline' });

const showEditor = computed({
    get: () => editing.value !== null,
    set: (v) => {
        if (!v) editing.value = null;
    },
});

const startAdd = () => {
    editing.value = 'new';
    draft.value = {
        label: '',
        color: '#6957EE',
        icon: iconOptions[0],
    };
};

const startEdit = (item) => {
    editing.value = item.id;
    draft.value = {
        label: item.label,
        color: item.color,
        icon: item.icon || iconOptions[0],
    };
};

const cancel = () => {
    editing.value = null;
};

const save = () => {
    if (!draft.value.label.trim()) return;
    if (editing.value === 'new') {
        const id = `${props.dictKey}_${Date.now()}`;
        const item = {
            id,
            label: draft.value.label.trim(),
            color: draft.value.color,
        };
        if (props.withIcon) item.icon = draft.value.icon;
        store.addDictItem(props.dictKey, item);
    } else {
        const patch = {
            label: draft.value.label.trim(),
            color: draft.value.color,
        };
        if (props.withIcon) patch.icon = draft.value.icon;
        store.updateDictItem(props.dictKey, editing.value, patch);
    }
    editing.value = null;
};

const remove = (id) => {
    store.removeDictItem(props.dictKey, id);
};
</script>

<template>
    <component :is="embedded ? 'div' : 'v-card'" :class="embedded ? '' : 'mb-4'">
        <div
            class="d-flex align-center justify-space-between"
            :class="embedded ? 'px-4 pt-4 pb-2' : 'px-5 pt-5 pb-2'"
        >
            <h2 v-if="!embedded && title" class="text-subtitle-1 font-weight-bold mb-0">{{ title }}</h2>
            <div v-else />
            <v-btn size="small" color="primary" variant="tonal" prepend-icon="mdi-plus" @click="startAdd">
                Добавить
            </v-btn>
        </div>

        <div :class="embedded ? 'px-2 pb-3' : 'px-3 pb-3'">
            <div
                v-for="item in items"
                :key="item.id"
                class="d-flex align-center ga-3 px-3 py-3"
                style="border-radius:11px"
            >
                <div
                    class="skydesk-stat-icon"
                    :style="{ background: item.color + '22' }"
                >
                    <v-icon
                        v-if="withIcon && item.icon"
                        :icon="item.icon"
                        size="18"
                        :color="item.color"
                    />
                    <span
                        v-else
                        style="width:12px;height:12px;border-radius:50%;display:inline-block"
                        :style="{ background: item.color }"
                    />
                </div>
                <div class="flex-grow-1">
                    <div class="text-body-2 font-weight-bold">{{ item.label }}</div>
                    <div class="text-caption text-medium-emphasis">{{ item.color }}</div>
                </div>
                <v-btn icon variant="text" size="small" @click="startEdit(item)">
                    <v-icon size="18">mdi-pencil-outline</v-icon>
                </v-btn>
                <v-btn icon variant="text" size="small" :disabled="items.length <= 1 || item.is_system" @click="remove(item.id)">
                    <v-icon size="18">mdi-delete-outline</v-icon>
                </v-btn>
            </div>
        </div>

        <v-dialog v-model="showEditor" :max-width="mdAndUp ? 440 : undefined" :fullscreen="!mdAndUp">
            <v-card v-if="editing !== null" class="pa-5">
                <div class="text-h6 font-weight-bold mb-4">
                    {{ editing === 'new' ? 'Новый элемент' : 'Редактирование' }}
                </div>
                <v-text-field v-model="draft.label" label="Название" class="mb-2" />
                <v-text-field v-model="draft.color" label="Цвет" type="color" class="mb-2" />
                <v-select
                    v-if="withIcon"
                    v-model="draft.icon"
                    :items="iconOptions"
                    label="Иконка"
                    class="mb-2"
                >
                    <template #selection="{ item }">
                        <v-icon start>{{ item.value }}</v-icon>{{ item.value }}
                    </template>
                    <template #item="{ props: ip, item }">
                        <v-list-item v-bind="ip" :prepend-icon="item.value" :title="item.value" />
                    </template>
                </v-select>
                <div class="d-flex justify-end ga-2 mt-2">
                    <v-btn variant="tonal" @click="cancel">Отмена</v-btn>
                    <v-btn color="primary" @click="save">Сохранить</v-btn>
                </div>
            </v-card>
        </v-dialog>
    </component>
</template>
