const DB_NAME = 'skydesk-offline';
const DB_VERSION = 1;
const PAGES = 'pages';
const META = 'meta';

function openDb() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);
        req.onupgradeneeded = () => {
            const db = req.result;
            if (!db.objectStoreNames.contains(PAGES)) {
                db.createObjectStore(PAGES, { keyPath: 'url' });
            }
            if (!db.objectStoreNames.contains(META)) {
                db.createObjectStore(META, { keyPath: 'key' });
            }
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

function txDone(tx) {
    return new Promise((resolve, reject) => {
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
        tx.onabort = () => reject(tx.error || new Error('aborted'));
    });
}

function normalizeUrl(url) {
    try {
        const u = typeof url === 'string' ? new URL(url, window.location.origin) : url;
        return u.pathname.replace(/\/$/, '') || '/';
    } catch {
        return String(url || '/').split('?')[0].replace(/\/$/, '') || '/';
    }
}

function cloneJson(value) {
    return JSON.parse(JSON.stringify(value));
}

function sanitizeProps(props) {
    const next = cloneJson(props || {});
    next.flash = {};
    next.errors = {};
    return next;
}

export async function savePageSnapshot(page) {
    if (typeof indexedDB === 'undefined' || !page?.component) return;
    if (!page.props?.auth?.user) return;

    const url = normalizeUrl(page.url);
    const db = await openDb();
    const tx = db.transaction([PAGES, META], 'readwrite');
    const savedAt = Date.now();

    tx.objectStore(PAGES).put({
        url,
        component: page.component,
        props: sanitizeProps(page.props),
        savedAt,
    });

    if (page.props.skydesk) {
        tx.objectStore(META).put({
            key: 'workspace',
            auth: cloneJson(page.props.auth),
            skydesk: cloneJson(page.props.skydesk),
            savedAt,
        });
    }

    await txDone(tx);
    db.close();
    return savedAt;
}

export async function getPageSnapshot(url) {
    if (typeof indexedDB === 'undefined') return null;
    const key = normalizeUrl(url);
    const db = await openDb();
    const tx = db.transaction(PAGES, 'readonly');
    const req = tx.objectStore(PAGES).get(key);
    const row = await new Promise((resolve, reject) => {
        req.onsuccess = () => resolve(req.result || null);
        req.onerror = () => reject(req.error);
    });
    await txDone(tx);
    db.close();
    return row;
}

export async function getWorkspaceSnapshot() {
    if (typeof indexedDB === 'undefined') return null;
    const db = await openDb();
    const tx = db.transaction(META, 'readonly');
    const req = tx.objectStore(META).get('workspace');
    const row = await new Promise((resolve, reject) => {
        req.onsuccess = () => resolve(req.result || null);
        req.onerror = () => reject(req.error);
    });
    await txDone(tx);
    db.close();
    return row;
}

export { normalizeUrl };
