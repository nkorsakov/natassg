/**
 * Стили для chip / badge по hex-цвету из словаря.
 * Vuetify `color` принимает имена темы, не hex — поэтому style.
 */
export function dictChipStyle(color, alpha = 0.16) {
    if (!color) return {};
    return {
        color,
        backgroundColor: withAlpha(color, alpha),
        borderColor: 'transparent',
    };
}

export function dictDotStyle(color) {
    if (!color) return { background: '#9A9BA3' };
    return { background: color };
}

export function dictTextStyle(color) {
    if (!color) return {};
    return { color };
}

/** Vuetify select slot item: raw может отсутствовать. */
export function selectItemColor(item) {
    return item?.raw?.color ?? item?.color ?? null;
}

export function selectItemIcon(item) {
    return item?.raw?.icon ?? item?.icon ?? 'mdi-checkbox-blank-circle-outline';
}

function withAlpha(hex, alpha) {
    const raw = String(hex).replace('#', '').trim();
    if (raw.length !== 3 && raw.length !== 6) {
        return hex;
    }
    const full = raw.length === 3
        ? raw.split('').map((c) => c + c).join('')
        : raw;
    const r = Number.parseInt(full.slice(0, 2), 16);
    const g = Number.parseInt(full.slice(2, 4), 16);
    const b = Number.parseInt(full.slice(4, 6), 16);
    if ([r, g, b].some((n) => Number.isNaN(n))) return hex;
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}
