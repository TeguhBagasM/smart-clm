<script setup lang="ts">
import BooleanFilter from '@/components/list/filters/BooleanFilter.vue';
import DateRangeFilter from '@/components/list/filters/DateRangeFilter.vue';
import MultiFilter from '@/components/list/filters/MultiFilter.vue';
import SelectFilter from '@/components/list/filters/SelectFilter.vue';
import type { DateRangeValue, FilterValue, ListFilter } from '@/types';

defineProps<{ filter: ListFilter }>();

defineEmits<{ change: [value: FilterValue] }>();
</script>

<template>
    <SelectFilter
        v-if="filter.type === 'select'"
        :label="filter.label"
        :options="filter.options"
        :model-value="filter.value as string | null"
        @update:model-value="$emit('change', $event)"
    />
    <MultiFilter
        v-else-if="filter.type === 'multi'"
        :label="filter.label"
        :options="filter.options"
        :model-value="filter.value as string[] | null"
        @update:model-value="$emit('change', $event)"
    />
    <BooleanFilter
        v-else-if="filter.type === 'boolean'"
        :label="filter.label"
        :model-value="filter.value as boolean | null"
        @update:model-value="$emit('change', $event)"
    />
    <DateRangeFilter
        v-else
        :label="filter.label"
        :model-value="filter.value as DateRangeValue | null"
        @update:model-value="$emit('change', $event)"
    />
</template>
