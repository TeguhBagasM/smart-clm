<script setup lang="ts">
import ConfigTabs from '@/components/documents/config/ConfigTabs.vue';
import Heading from '@/components/Heading.vue';

defineProps<{
    active: 'types' | 'numbering' | 'clauses' | 'departments';
    title: string;
    description: string;
}>();
</script>

<template>
    <div class="flex flex-col gap-5">
        <div
            class="flex flex-wrap items-start justify-between gap-3 [&>header]:mb-0"
        >
            <Heading :title="title" :description="description" />
            <slot name="action" />
        </div>
        <ConfigTabs :active="active" />
    </div>
</template>
