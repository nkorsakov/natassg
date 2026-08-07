import { computed, onMounted, onUnmounted, readonly, ref } from 'vue';

const isOnline = ref(typeof navigator === 'undefined' ? true : navigator.onLine);
const lastSyncedAt = ref(null);
let listenersAttached = false;

function syncOnlineFlag() {
    isOnline.value = typeof navigator === 'undefined' ? true : navigator.onLine;
}

function attachGlobalListeners() {
    if (listenersAttached || typeof window === 'undefined') return;
    listenersAttached = true;
    window.addEventListener('online', syncOnlineFlag);
    window.addEventListener('offline', syncOnlineFlag);
}

export function setLastSyncedAt(ts) {
    lastSyncedAt.value = ts || null;
}

export function requireOnline(message = 'Изменения доступны только при подключении к интернету') {
    syncOnlineFlag();
    if (isOnline.value) return true;
    window.alert(message);
    return false;
}

export function useOffline() {
    attachGlobalListeners();

    onMounted(() => {
        syncOnlineFlag();
        attachGlobalListeners();
    });

    const lastSyncedLabel = computed(() => {
        if (!lastSyncedAt.value) return null;
        try {
            return new Date(lastSyncedAt.value).toLocaleString('ru-RU', {
                day: 'numeric',
                month: 'short',
                hour: '2-digit',
                minute: '2-digit',
            });
        } catch {
            return null;
        }
    });

    return {
        isOnline: readonly(isOnline),
        lastSyncedAt: readonly(lastSyncedAt),
        lastSyncedLabel,
        requireOnline,
    };
}

attachGlobalListeners();
