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

function apply(theme) {
    theme.global.name.value = mode.value === 'dark' ? 'skydeskDark' : 'skydesk';

    const accentColor = ACCENTS.find((a) => a.id === accent.value)?.color ?? ACCENTS[0].color;
    for (const name of ['skydesk', 'skydeskDark']) {
        const colors = theme.themes.value[name]?.colors;
        if (!colors) continue;
        colors.primary = accentColor;
        colors.info = accentColor;
    }

    document.documentElement.dataset.theme = mode.value;
    document.documentElement.style.setProperty('--skydesk-accent', accentColor);
}

export function useAppearance() {
    const theme = useTheme();

    if (!wired) {
        load();
        apply(theme);
        watch([mode, accent], () => {
            apply(theme);
            save();
        });
        wired = true;
    }

    const isDark = computed(() => mode.value === 'dark');
    const currentAccent = computed(
        () => ACCENTS.find((a) => a.id === accent.value) ?? ACCENTS[0],
    );

    const setMode = (value) => {
        mode.value = value;
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
