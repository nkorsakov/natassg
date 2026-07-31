<script setup>
import { useAppearance } from '@/composables/useAppearance';

const { mode, accent, accents, setMode, setAccent } = useAppearance();
</script>

<template>
    <v-menu location="bottom end" :close-on-content-click="false" offset="8">
        <template #activator="{ props: menuProps }">
            <v-btn
                v-bind="menuProps"
                icon
                variant="outlined"
                size="small"
                color="secondary"
                aria-label="Оформление"
            >
                <v-icon>mdi-palette-outline</v-icon>
            </v-btn>
        </template>

        <v-card min-width="240" class="pa-4">
            <div class="text-caption font-weight-bold text-medium-emphasis mb-2">Тема</div>
            <v-btn-toggle
                :model-value="mode"
                mandatory
                density="comfortable"
                color="primary"
                variant="outlined"
                divided
                class="w-100 mb-4"
                @update:model-value="setMode"
            >
                <v-btn value="light" class="flex-grow-1" prepend-icon="mdi-white-balance-sunny">
                    Светлая
                </v-btn>
                <v-btn value="dark" class="flex-grow-1" prepend-icon="mdi-moon-waning-crescent">
                    Тёмная
                </v-btn>
            </v-btn-toggle>

            <div class="text-caption font-weight-bold text-medium-emphasis mb-2">Цвет кнопок</div>
            <div class="d-flex flex-wrap ga-2">
                <button
                    v-for="item in accents"
                    :key="item.id"
                    type="button"
                    class="skydesk-swatch"
                    :class="{ 'skydesk-swatch--active': accent === item.id }"
                    :style="{ background: item.color }"
                    :title="item.label"
                    :aria-label="item.label"
                    @click="setAccent(item.id)"
                >
                    <v-icon v-if="accent === item.id" size="16" color="white">mdi-check</v-icon>
                </button>
            </div>
        </v-card>
    </v-menu>
</template>

<style scoped>
.skydesk-swatch {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 2px solid transparent;
    cursor: pointer;
    display: grid;
    place-items: center;
    padding: 0;
    outline: none;
    box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.12);
}

.skydesk-swatch--active {
    box-shadow:
        0 0 0 2px rgb(var(--v-theme-surface)),
        0 0 0 4px currentColor;
    color: inherit;
    outline: 2px solid rgb(var(--v-theme-on-surface));
    outline-offset: 2px;
}
</style>
