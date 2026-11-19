<script setup lang="ts">
import { Button } from '@/components/ui/button';

export type Zoom = 'fit' | 1 | 1.5;

const model = defineModel<Zoom>({ required: true });

const options: { value: Zoom; label: string }[] = [
    { value: 'fit', label: 'Sesuaikan' },
    { value: 1, label: '100%' },
    { value: 1.5, label: '150%' },
];
</script>

<template>
    <div class="flex items-center gap-1" role="group" aria-label="Zoom kanvas">
        <Button
            v-for="o in options"
            :key="String(o.value)"
            type="button"
            size="sm"
            :variant="model === o.value ? 'secondary' : 'ghost'"
            :aria-pressed="model === o.value"
            @click="model = o.value"
        >
            {{ o.label }}
        </Button>
    </div>
</template>
