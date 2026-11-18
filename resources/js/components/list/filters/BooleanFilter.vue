<script setup lang="ts">
import SelectFilter from '@/components/list/filters/SelectFilter.vue';

defineProps<{ label: string; modelValue: boolean | null }>();

defineEmits<{ 'update:modelValue': [value: boolean | null] }>();

const options = [
    { value: '1', label: 'Ya' },
    { value: '0', label: 'Tidak' },
];
</script>

<template>
    <SelectFilter
        :label="label"
        :options="options"
        :model-value="modelValue === null ? null : modelValue ? '1' : '0'"
        @update:model-value="
            $emit('update:modelValue', $event === null ? null : $event === '1')
        "
    />
</template>
