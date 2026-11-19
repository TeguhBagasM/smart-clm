<script setup lang="ts">
import SearchSelect from '@/components/SearchSelect.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { FilterOption } from '@/types';

const ALL = '__all__';

defineProps<{
    label: string;
    options: FilterOption[];
    modelValue: string | null;
}>();

defineEmits<{ 'update:modelValue': [value: string | null] }>();
</script>

<template>
    <SearchSelect
        :model-value="modelValue ?? ALL"
        :options="[
            { value: ALL, label: 'Semua' },
            ...options.map((o) => ({ value: String(o.value), label: o.label })),
        ]"
        :label="label"
        :search-placeholder="`Cari ${label.toLowerCase()}…`"
        @update:model-value="
            $emit('update:modelValue', $event === ALL ? null : $event)
        "
    />
</template>
