<script setup lang="ts">
import { ArrowDown, ArrowUp, Columns2, Rows2 } from '@lucide/vue';
import NumberStepper from '@/components/documents/config/header/NumberStepper.vue';
import { Button } from '@/components/ui/button';

const PT_PER_MM = 2.835;

defineProps<{
    row: number;
    height: number;
    rowCount: number;
    cellCount: number;
}>();
defineEmits<{
    height: [value: number];
    move: [step: -1 | 1];
    addRow: [];
    addColumn: [];
}>();
</script>

<template>
    <section class="flex flex-col gap-3 border-t pt-4">
        <h3 class="text-sm font-medium">Baris {{ row + 1 }}</h3>
        <NumberStepper
            :model-value="height"
            :label="`Tinggi baris (≈ ${(height / PT_PER_MM).toFixed(1)} mm)`"
            unit="pt"
            :min="12"
            :max="60"
            slider
            @update:model-value="$emit('height', $event)"
        />
        <div class="flex flex-wrap gap-2">
            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="row === 0"
                @click="$emit('move', -1)"
                ><ArrowUp /> Naikkan</Button
            >
            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="row === rowCount - 1"
                @click="$emit('move', 1)"
                ><ArrowDown /> Turunkan</Button
            >
            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="rowCount >= 12"
                @click="$emit('addRow')"
                ><Rows2 /> Tambah baris di bawah</Button
            >
            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="cellCount >= 4"
                @click="$emit('addColumn')"
                ><Columns2 /> Tambah kolom</Button
            >
        </div>
    </section>
</template>
