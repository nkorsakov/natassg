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
/** @type {import('vuetify').ThemeInstance | null} */
let themeApi = null;

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
    try {
        localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify({ mode: mode.value, accent: accent.value }),
        );
    } catch {
        // ignore
    }
}

function accentColor() {
    return ACCENTS.find((a) => a.id === accent.value)?.color ?? ACCENTS[0].color;
}

function patchThemeColors(theme, color) {
    const themes = theme?.themes?.value;
    if (!themes) return;

    for (const name of ['light', 'dark']) {
        const current = themes[name];
        if (!current?.colors) continue;
        // Replace the theme object so Vuetify's computedThemes/styles always re-run.
        themes[name] = {
            ...current,
            colors: {
                ...current.colors,
                primary: color,
                info: color,
            },
        };
    }

    // Touch the root ref for shallow watchers.
    theme.themes.value = { ...themes };
}

function applyAccent(theme = themeApi) {
    if (!theme) return;
    const color = accentColor();
    patchThemeColors(theme, color);
    document.documentElement.style.setProperty('--skydesk-accent', color);
}

async function apply(theme = themeApi) {
    if (!theme) return;

    const themeName = mode.value === 'dark' ? 'dark' : 'light';

    try {
        // Avoid Vuetify view-transitions: change(false) still animates if origin was set.
        theme.setTransitionOrigin?.(null);
        if (theme.global?.name) {
            theme.global.name.value = themeName;
        } else if (typeof theme.change === 'function') {
            await theme.change(themeName, false);
        }
    } catch {
        try {
            await theme.change?.(themeName, false);
        } catch {
            // ignore
        }
    }

    applyAccent(theme);
    document.documentElement.dataset.theme = mode.value;
    document.documentElement.style.colorScheme = mode.value;
}

export function useAppearance() {
    const theme = useTheme();
    themeApi = theme;

    if (!wired) {
        load();
        apply(theme);
        watch(mode, () => {
            apply(themeApi);
            save();
        });
        watch(accent, () => {
            applyAccent(themeApi);
            save();
        });
        wired = true;
    } else {
        // Keep theme instance fresh after remounts / HMR.
        applyAccent(theme);
    }

    const isDark = computed(() => mode.value === 'dark');
    const currentAccent = computed(
        () => ACCENTS.find((a) => a.id === accent.value) ?? ACCENTS[0],
    );

    const setMode = (value) => {
        const next = Array.isArray(value) ? value[0] : value;
        if (next !== 'light' && next !== 'dark') return;
        if (mode.value === next) {
            // Force re-apply if UI got out of sync with Vuetify.
            apply(themeApi);
            return;
        }
        mode.value = next;
    };

    const toggleMode = () => {
        mode.value = mode.value === 'dark' ? 'light' : 'dark';
    };

    const setAccent = (id) => {
        if (!ACCENTS.some((a) => a.id === id)) return;
        if (accent.value === id) {
            applyAccent(themeApi);
            return;
        }
        accent.value = id;
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
