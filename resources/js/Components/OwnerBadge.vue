<script setup>
defineProps({
    user: { type: Object, default: null },
    show: { type: Boolean, default: false },
});
</script>

<template>
    <v-chip
        v-if="show && user"
        size="x-small"
        variant="tonal"
        color="secondary"
        class="flex-shrink-0"
        :title="user.name"
    >
        {{ user.initials || user.name }}
    </v-chip>
</template>
