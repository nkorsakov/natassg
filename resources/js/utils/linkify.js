/**
 * Разбивает текст на фрагменты: обычный текст и URL.
 * @returns {{ type: 'text' | 'link', value: string, href?: string }[]}
 */
export function linkifyParts(text) {
    const source = String(text || '');
    if (!source) return [];

    const re = /(https?:\/\/[^\s]+|www\.[^\s]+)/gi;
    const parts = [];
    let lastIndex = 0;
    let match;

    while ((match = re.exec(source)) !== null) {
        if (match.index > lastIndex) {
            parts.push({ type: 'text', value: source.slice(lastIndex, match.index) });
        }

        const raw = match[0].replace(/[),.]+$/g, '');
        const trailing = match[0].slice(raw.length);
        const href = raw.startsWith('http') ? raw : `https://${raw}`;

        parts.push({ type: 'link', value: raw, href });
        if (trailing) {
            parts.push({ type: 'text', value: trailing });
        }

        lastIndex = match.index + match[0].length;
    }

    if (lastIndex < source.length) {
        parts.push({ type: 'text', value: source.slice(lastIndex) });
    }

    return parts.length ? parts : [{ type: 'text', value: source }];
}
