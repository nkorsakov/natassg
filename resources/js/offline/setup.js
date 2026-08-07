import { router } from '@inertiajs/vue3';
import { defaultOfflineProps, offlineComponentFor } from './pages';
import {
    getPageSnapshot,
    getWorkspaceSnapshot,
    normalizeUrl,
    savePageSnapshot,
} from './snapshotDb';
import { requireOnline, setLastSyncedAt } from '@/composables/useOffline';

async function navigateOffline(rawUrl) {
    const path = normalizeUrl(rawUrl);
    const [pageSnap, workspace] = await Promise.all([
        getPageSnapshot(path),
        getWorkspaceSnapshot(),
    ]);

    const component = pageSnap?.component || offlineComponentFor(path);
    if (!component || !workspace?.skydesk) {
        window.alert(
            'Нет сохранённых данных для офлайн-просмотра. Откройте раздел онлайн хотя бы раз.',
        );
        return;
    }

    const baseProps = {
        ...defaultOfflineProps(path),
        ...(pageSnap?.props || {}),
    };

    router.push({
        url: path,
        component,
        props: {
            ...baseProps,
            auth: workspace.auth || baseProps.auth || { user: null },
            skydesk: workspace.skydesk,
            flash: {},
            errors: {},
        },
        preserveScroll: false,
        preserveState: false,
    });
}

async function hydrateFromSnapshot(initialPage) {
    try {
        if (initialPage?.props?.auth?.user) {
            const ts = await savePageSnapshot(initialPage);
            if (ts) setLastSyncedAt(ts);
        }

        const workspace = await getWorkspaceSnapshot();
        if (!workspace?.skydesk || !workspace.savedAt) return;
        setLastSyncedAt(workspace.savedAt);

        if (!initialPage?.props?.auth?.user) return;

        const offline = typeof navigator !== 'undefined' && !navigator.onLine;
        if (offline || !initialPage.props.skydesk) {
            router.replaceProp('skydesk', workspace.skydesk);
            if (workspace.auth) {
                router.replaceProp('auth', workspace.auth);
            }
        }
    } catch {
        // ignore storage errors
    }
}

export function setupOffline(initialPage = null) {
    router.on('success', (event) => {
        const page = event.detail?.page;
        if (!page) return;
        void savePageSnapshot(page).then((ts) => {
            if (ts) setLastSyncedAt(ts);
        });
    });

    router.on('before', (event) => {
        if (typeof navigator === 'undefined' || navigator.onLine) return;

        const visit = event.detail.visit;
        const method = String(visit.method || 'get').toLowerCase();

        if (method !== 'get') {
            event.preventDefault();
            requireOnline();
            return;
        }

        event.preventDefault();
        const href = visit.url instanceof URL
            ? visit.url.pathname
            : (visit.url?.pathname || visit.url || window.location.href);
        void navigateOffline(href);
    });

    void hydrateFromSnapshot(initialPage);
}

export { navigateOffline, requireOnline };
