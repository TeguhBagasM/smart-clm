<script setup lang="ts">
import { Button } from '@/components/ui/button';

const model = defineModel<string>({ required: true });

defineProps<{ options: [value: string, label: string][]; label?: string }>();
</script>

<template>
    <div
        role="radiogroup"
        :aria-label="label"
        class="grid gap-2"
        :style="{
            gridTemplateColumns: `repeat(${options.length}, minmax(0, 1fr))`,
        }"
    >
        <Button
            v-for="[value, text] in options"
            :key="value"
            type="button"
            role="radio"
            :aria-checked="model === value"
            :variant="model === value ? 'default' : 'outline'"
            @click="model = value"
        >
            {{ text }}
        </Button>
    </div>
</template>
