/**
 * Yandex Metrika helpers for SPA hits and conversion goals.
 * Create matching goals in Metrika dashboard (JavaScript event / reachGoal).
 */
export const METRIKA_ID = 111424783;

function ymReady() {
    return typeof window !== 'undefined' && typeof window.ym === 'function';
}

export function reachGoal(name, params) {
    if (!name || !ymReady()) {
        return;
    }

    try {
        if (params) {
            window.ym(METRIKA_ID, 'reachGoal', name, params);
        } else {
            window.ym(METRIKA_ID, 'reachGoal', name);
        }
    } catch {
        // ignore tracker errors
    }
}

export function hit(url, options = {}) {
    if (!ymReady()) {
        return;
    }

    try {
        window.ym(METRIKA_ID, 'hit', url || window.location.href, {
            title: options.title || document.title,
            referer: options.referer || document.referrer,
        });
    } catch {
        // ignore tracker errors
    }
}

/**
 * Track Inertia client navigations (first paint is covered by Metrika init).
 */
export function setupMetrikaSpaHits(router) {
    if (!router?.on) {
        return;
    }

    let previousUrl = window.location.href;
    let isInitial = true;

    router.on('navigate', (event) => {
        // Inertia fires `navigate` on initial load too; that view is already counted by `init`.
        if (isInitial) {
            isInitial = false;
            return;
        }

        const pageUrl = event?.detail?.page?.url;
        const href = typeof pageUrl === 'string'
            ? pageUrl
            : `${window.location.pathname}${window.location.search}`;
        const url = href.startsWith('http') ? href : `${window.location.origin}${href}`;

        hit(url, { referer: previousUrl });
        previousUrl = url;
    });
}
