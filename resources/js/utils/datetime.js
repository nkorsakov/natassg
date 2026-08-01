/** Разбор/сборка `YYYY-MM-DD` / `YYYY-MM-DDTHH:mm` без AM/PM. */

export function splitDateTime(value) {
    if (!value) return { date: '', time: '' };
    const s = String(value);
    if (s.includes('T')) {
        const [date, timePart = ''] = s.split('T');
        return { date, time: timePart.slice(0, 5) };
    }
    return { date: s.slice(0, 10), time: '' };
}

export function joinDateTime(date, time, { allDay = false } = {}) {
    if (!date) return '';
    if (allDay) return date;
    const t = normalizeTime(time) || '10:00';
    return `${date}T${t}`;
}

export function normalizeTime(value) {
    const raw = String(value || '').trim();
    if (!raw) return '';

    const match = raw.match(/^(\d{1,2})[:.](\d{1,2})$/);
    if (!match) return '';

    const h = Math.min(23, Math.max(0, Number(match[1])));
    const m = Math.min(59, Math.max(0, Number(match[2])));
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
}

/** Отображение `YYYY-MM-DD` / `YYYY-MM-DDTHH:mm` → `дд.мм.гггг` (+ время). */
export function formatDisplayDate(value, { allDay = false, withTime = true } = {}) {
    if (!value) return '';
    const s = String(value);
    const datePart = s.slice(0, 10);
    const [y, m, d] = datePart.split('-').map(Number);
    if (!y || !m || !d) return '';

    const dateStr = `${String(d).padStart(2, '0')}.${String(m).padStart(2, '0')}.${y}`;
    if (allDay || !withTime || !s.includes('T')) return dateStr;

    const time = s.slice(11, 16);
    return time ? `${dateStr} ${time}` : dateStr;
}
