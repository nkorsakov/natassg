import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useIsAdmin() {
    const page = usePage();
    const isAdmin = computed(() => !!page.props.auth?.user?.is_admin);

    return { isAdmin };
}
