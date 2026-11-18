<script setup lang="ts">
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
    <Select
        :model-value="modelValue ?? ALL"
        @update:model-value="
            $emit('update:modelValue', $event === ALL ? null : String($event))
        "
    >
        <SelectTrigger class="w-full" :aria-label="label">
            <SelectValue />
        </SelectTrigger>
        <SelectContent>
            <SelectItem :value="ALL">Semua</SelectItem>
            <SelectItem
                v-for="option in options"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
