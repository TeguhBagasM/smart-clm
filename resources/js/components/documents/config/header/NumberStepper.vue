<script setup lang="ts">
import { Minus, Plus } from '@lucide/vue';
import { computed, useId } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

/** The one control for every number of the letterhead: typed, -/+ buttons and an optional slider. */
const model = defineModel<number>({ required: true });

const props = withDefaults(
    defineProps<{
        label: string;
        min: number;
        max: number;
        step?: number;
        unit?: string;
        hint?: string;
        slider?: boolean;
    }>(),
    { step: 1, unit: '', hint: '', slider: false },
);

const id = useId();
const decimals = computed(() => (props.step < 1 ? 2 : 0));

function set(value: number): void {
    if (Number.isNaN(value)) return;

    const clamped = Math.min(props.max, Math.max(props.min, value));
    model.value = Number(clamped.toFixed(decimals.value));
}
</script>

<template>
    <div class="flex min-w-0 flex-col gap-1.5">
        <label :for="id" class="text-sm font-medium">{{ label }}</label>
        <div class="flex items-center gap-1.5">
            <Button
                type="button"
                variant="outline"
                size="icon"
                class="size-9 shrink-0"
                :aria-label="`Kurangi ${label}`"
                :disabled="model <= min"
                @click="set(model - step)"
            >
                <Minus />
            </Button>
            <Input
                :id="id"
                type="number"
                inputmode="decimal"
                :min="min"
                :max="max"
                :step="step"
                :model-value="model"
                class="h-9 w-20 min-w-16 shrink-0 [appearance:textfield] px-1 text-center tabular-nums [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                @change="set(Number(($event.target as HTMLInputElement).value))"
            />
            <Button
                type="button"
                variant="outline"
                size="icon"
                class="size-9 shrink-0"
                :aria-label="`Tambah ${label}`"
                :disabled="model >= max"
                @click="set(model + step)"
            >
                <Plus />
            </Button>
            <span
                v-if="unit"
                class="text-sm whitespace-nowrap text-muted-foreground"
            >
                {{ unit }}
            </span>
        </div>
        <div v-if="slider" class="flex flex-col">
            <input
                type="range"
                :min="min"
                :max="max"
                :step="step"
                :value="model"
                :aria-label="label"
                class="w-full min-w-0 accent-primary"
                @input="set(Number(($event.target as HTMLInputElement).value))"
            />
            <span
                class="flex justify-between text-xs text-muted-foreground tabular-nums"
            >
                <span>{{ min }}{{ unit }}</span>
                <span>{{ max }}{{ unit }}</span>
            </span>
        </div>
        <p v-if="hint" class="text-xs text-muted-foreground">{{ hint }}</p>
    </div>
</template>
