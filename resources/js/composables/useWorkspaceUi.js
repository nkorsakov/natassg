import { ref } from 'vue';

const taskOpen = ref(false);
const taskId = ref(null);
const eventOpen = ref(false);
const eventId = ref(null);
const advanceOpen = ref(false);
const advanceId = ref(null);
const contactOpen = ref(false);
const contactId = ref(null);
const quickCreateOpen = ref(false);
const quickCreateEventId = ref(null);

export function useWorkspaceUi() {
    const openTask = (id) => {
        taskId.value = id;
        taskOpen.value = true;
    };

    const openEvent = (id) => {
        eventId.value = id;
        eventOpen.value = true;
    };

    const openAdvance = (id) => {
        advanceId.value = id;
        advanceOpen.value = true;
    };

    const openContact = (id) => {
        contactId.value = id;
        contactOpen.value = true;
    };

    const openQuickCreate = (eventIdForLink = null) => {
        quickCreateEventId.value = eventIdForLink;
        quickCreateOpen.value = true;
    };

    return {
        taskOpen,
        taskId,
        eventOpen,
        eventId,
        advanceOpen,
        advanceId,
        contactOpen,
        contactId,
        quickCreateOpen,
        quickCreateEventId,
        openTask,
        openEvent,
        openAdvance,
        openContact,
        openQuickCreate,
    };
}
