<script setup lang="ts">
import { Checkbox } from '@/components/ui/checkbox';

/** Several checkboxes sent as `name[]` in a plain form; each row is a 44px touch target. */
defineProps<{
    name: string;
    legend: string;
    options: { value: number; label: string }[];
    selected: number[];
    emptyText?: string;
}>();
</script>

<template>
    <fieldset class="flex flex-col gap-1">
        <legend class="mb-1 text-sm font-medium">{{ legend }}</legend>
        <p v-if="options.length === 0" class="text-sm text-muted-foreground">
            {{ emptyText }}
        </p>
        <ul class="flex max-h-60 flex-col overflow-y-auto rounded-lg border">
            <li
                v-for="option in options"
                :key="option.value"
                class="border-b last:border-b-0"
            >
                <label
                    class="flex min-h-11 cursor-pointer items-center gap-3 px-3 text-sm"
                >
                    <Checkbox
                        :name="`${name}[]`"
                        :value="String(option.value)"
                        :default-value="selected.includes(option.value)"
                    />
                    {{ option.label }}
                </label>
            </li>
        </ul>
    </fieldset>
</template>
