import { computed, ref, watch } from 'vue';
import { useTheme } from 'vuetify';

export const ACCENTS = [
    { id: 'violet', label: 'Фиолетовый', color: '#6957EE' },
    { id: 'blue', label: 'Синий', color: '#3B82F6' },
    { id: 'teal', label: 'Бирюзовый', color: '#0D9488' },
    { id: 'green', label: 'Зелёный', color: '#37A878' },
    { id: 'orange', label: 'Оранжевый', color: '#E67E22' },
    { id: 'rose', label: 'Розовый', color: '#E11D48' },
];

const STORAGE_KEY = 'skydesk-appearance';

const mode = ref('light');
const accent = ref('violet');
let wired = false;

function load() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return;
        const data = JSON.parse(raw);
        if (data.mode === 'light' || data.mode === 'dark') mode.value = data.mode;
        if (ACCENTS.some((a) => a.id === data.accent)) accent.value = data.accent;
    } catch {
        // ignore
    }
}

function save() {
    localStorage.setItem(
        STORAGE_KEY,
        JSON.stringify({ mode: mode.value, accent: accent.value }),
    );
}

function applyAccent(theme) {
    const accentColor = ACCENTS.find((a) => a.id === accent.value)?.color ?? ACCENTS[0].color;
    for (const name of ['light', 'dark']) {
        const colors = theme.themes.value[name]?.colors;
        if (!colors) continue;
        colors.primary = accentColor;
        colors.info = accentColor;
    }
    document.documentElement.style.setProperty('--skydesk-accent', accentColor);
}

function apply(theme) {
    const themeName = mode.value === 'dark' ? 'dark' : 'light';
    if (typeof theme.change === 'function') {
        theme.change(themeName);
    } else {
        theme.global.name.value = themeName;
    }
    applyAccent(theme);
    document.documentElement.dataset.theme = mode.value;
    document.documentElement.style.colorScheme = mode.value;
}

export function useAppearance() {
    const theme = useTheme();

    if (!wired) {
        load();
        apply(theme);
        watch(mode, () => {
            apply(theme);
            save();
        });
        watch(accent, () => {
            applyAccent(theme);
            save();
        });
        wired = true;
    }

    const isDark = computed(() => mode.value === 'dark');
    const currentAccent = computed(
        () => ACCENTS.find((a) => a.id === accent.value) ?? ACCENTS[0],
    );

    const setMode = (value) => {
        if (value === 'light' || value === 'dark') mode.value = value;
    };

    const toggleMode = () => {
        mode.value = mode.value === 'dark' ? 'light' : 'dark';
    };

    const setAccent = (id) => {
        if (ACCENTS.some((a) => a.id === id)) accent.value = id;
    };

    return {
        mode,
        accent,
        isDark,
        currentAccent,
        accents: ACCENTS,
        setMode,
        toggleMode,
        setAccent,
    };
}
