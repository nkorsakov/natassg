/** Маршруты приложения, доступные офлайн через clientVisit. */
export const OFFLINE_PAGES = {
    '/dashboard': 'Dashboard/Index',
    '/tasks': 'Tasks/Index',
    '/calendar': 'Calendar/Index',
    '/finance': 'Finance/Index',
    '/contacts': 'Contacts/Index',
    '/settings': 'Settings/Index',
    '/reports': 'Reports/Compose',
};

export function offlineComponentFor(url) {
    return OFFLINE_PAGES[url] || null;
}

/** Props, которых нет в shared skydesk, но страница ожидает при первом офлайн-заходе. */
export function defaultOfflineProps(url) {
    if (url === '/settings') {
        return { users: [] };
    }
    if (url === '/reports') {
        const to = new Date();
        const from = new Date();
        from.setDate(from.getDate() - 7);
        const iso = (d) =>
            `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        return {
            period_from: iso(from),
            period_to: iso(to),
            preview: {
                period_from: iso(from),
                period_to: iso(to),
                tasks: [],
                events: [],
                advances: [],
                expenses: [],
                wallet: null,
            },
            recent: [],
            created: null,
        };
    }
    return {};
}
