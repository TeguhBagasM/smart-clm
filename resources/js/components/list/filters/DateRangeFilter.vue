<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { DateRangeValue } from '@/types';

const props = defineProps<{
    label: string;
    modelValue: DateRangeValue | null;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: DateRangeValue] }>();

function change(side: 'from' | 'to', value: string | number): void {
    emit('update:modelValue', {
        from: props.modelValue?.from ?? null,
        to: props.modelValue?.to ?? null,
        [side]: String(value) || null,
    });
}
</script>

<template>
    <div class="grid grid-cols-2 gap-2" role="group" :aria-label="label">
        <Label class="flex flex-col gap-1 text-xs font-normal">
            Dari
            <Input
                type="date"
                :model-value="modelValue?.from ?? ''"
                :max="modelValue?.to ?? undefined"
                @update:model-value="change('from', $event)"
            />
        </Label>
        <Label class="flex flex-col gap-1 text-xs font-normal">
            Sampai
            <Input
                type="date"
                :model-value="modelValue?.to ?? ''"
                :min="modelValue?.from ?? undefined"
                @update:model-value="change('to', $event)"
            />
        </Label>
    </div>
</template>
