<script setup>
defineProps({
    title: { type: String, required: true },
    meta: { type: String, default: '' },
    pills: { type: Array, default: () => [] },
    date: { type: String, default: '' },
    assignee: { type: String, default: '' },
    showSideMeta: { type: Boolean, default: false },
});

defineEmits(['toggle']);

const pillColor = (kind) => {
    if (kind === 'urgent') return 'error';
    if (kind === 'money') return 'warning';
    if (kind === 'wait') return 'primary';
    if (kind === 'green') return 'success';
    return 'secondary';
};
</script>

<template>
    <div class="skydesk-task d-flex align-center ga-3 px-3 py-3" style="border-radius:11px;min-height:56px">
        <v-btn
            icon
            size="x-small"
            variant="outlined"
            color="secondary"
            style="border-radius:50%"
            @click="$emit('toggle')"
        >
            <v-icon size="14">mdi-check</v-icon>
        </v-btn>

        <div class="flex-grow-1" style="min-width:0">
            <div class="text-body-2 font-weight-bold text-truncate">{{ title }}</div>
            <div class="text-caption text-medium-emphasis mt-1 d-flex align-center ga-2 flex-wrap">
                <span v-if="meta">{{ meta }}</span>
                <template v-if="!showSideMeta">
                    <v-chip
                        v-for="pill in pills"
                        :key="pill.label"
                        size="x-small"
                        class="skydesk-pill"
                        :color="pillColor(pill.kind)"
                        variant="tonal"
                    >
                        {{ pill.label }}
                    </v-chip>
                </template>
            </div>
        </div>

        <template v-if="showSideMeta">
            <div class="text-caption text-medium-emphasis d-none d-md-block text-no-wrap">{{ date }}</div>
            <v-chip
                v-for="pill in pills"
                :key="pill.label"
                size="x-small"
                class="skydesk-pill d-none d-md-inline-flex"
                :color="pillColor(pill.kind)"
                variant="tonal"
            >
                {{ pill.label }}
            </v-chip>
            <v-avatar v-if="assignee" size="25" color="primary" variant="tonal" class="d-none d-md-flex">
                <span style="font-size:10px;font-weight:800">{{ assignee }}</span>
            </v-avatar>
        </template>

        <v-btn icon variant="text" size="small" color="secondary">
            <v-icon>mdi-dots-horizontal</v-icon>
        </v-btn>
    </div>
</template>
