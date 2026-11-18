<script setup lang="ts">
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { FilterOption } from '@/types';

const props = defineProps<{
    label: string;
    options: FilterOption[];
    modelValue: string[] | null;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: string[]] }>();

function toggle(value: string, checked: boolean): void {
    const current = props.modelValue ?? [];

    emit(
        'update:modelValue',
        checked
            ? [...current, value]
            : current.filter((item) => item !== value),
    );
}
</script>

<template>
    <div
        class="flex max-h-48 flex-col gap-2 overflow-y-auto"
        role="group"
        :aria-label="label"
    >
        <Label
            v-for="option in options"
            :key="option.value"
            class="flex items-center gap-2 font-normal"
        >
            <Checkbox
                :model-value="modelValue?.includes(option.value) ?? false"
                @update:model-value="toggle(option.value, $event === true)"
            />
            {{ option.label }}
        </Label>
        <p v-if="options.length === 0" class="text-sm text-muted-foreground">
            Belum ada pilihan.
        </p>
    </div>
</template>
