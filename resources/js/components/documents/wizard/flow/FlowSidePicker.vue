<script setup lang="ts">
import { ArrowDown, ArrowLeft, ArrowRight, ArrowUp } from '@lucide/vue';
import type { FlowSide } from '@/types';

defineProps<{ label: string; value?: FlowSide }>();
const emit = defineEmits<{ change: [side: FlowSide | null] }>();

const SIDES = [
    { side: 'top', icon: ArrowUp, name: 'atas' },
    { side: 'right', icon: ArrowRight, name: 'kanan' },
    { side: 'bottom', icon: ArrowDown, name: 'bawah' },
    { side: 'left', icon: ArrowLeft, name: 'kiri' },
] as const;
</script>

<template>
    <div class="flex items-center gap-2">
        <span class="w-20 shrink-0 text-xs text-muted-foreground">{{
            label
        }}</span>
        <div class="flex gap-1" role="radiogroup" :aria-label="label">
            <button
                type="button"
                role="radio"
                :aria-checked="!value"
                class="h-8 rounded-md border px-2 text-xs aria-checked:border-primary aria-checked:bg-accent aria-checked:text-primary"
                title="Dipilih otomatis agar tidak bertabrakan"
                @click="emit('change', null)"
            >
                Otomatis
            </button>
            <button
                v-for="item in SIDES"
                :key="item.side"
                type="button"
                role="radio"
                :aria-checked="value === item.side"
                :aria-label="`${label} sisi ${item.name}`"
                :title="`Sisi ${item.name}`"
                class="flex size-8 items-center justify-center rounded-md border aria-checked:border-primary aria-checked:bg-accent aria-checked:text-primary"
                @click="emit('change', item.side)"
            >
                <component :is="item.icon" class="size-3.5" />
            </button>
        </div>
    </div>
</template>
