<script setup lang="ts">
import {
    ALL_SIDES,
    NO_SIDES,
} from '@/components/documents/config/header/headerBorders';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { HeaderSides } from '@/types';

const props = defineProps<{ borders: HeaderSides }>();
const emit = defineEmits<{ update: [borders: HeaderSides] }>();

const quick = [
    { label: 'Semua', sides: ALL_SIDES },
    { label: 'Tanpa garis', sides: NO_SIDES },
    { label: 'Hanya bawah', sides: { ...NO_SIDES, bottom: true } },
] as const;
const sides = [
    { key: 'top', label: 'Atas' },
    { key: 'bottom', label: 'Bawah' },
    { key: 'left', label: 'Kiri' },
    { key: 'right', label: 'Kanan' },
] as const;

const same = (a: HeaderSides, b: HeaderSides): boolean =>
    sides.every((side) => a[side.key] === b[side.key]);
</script>

<template>
    <fieldset class="flex flex-col gap-2">
        <legend class="text-sm font-medium">Garis sel</legend>
        <div class="flex flex-wrap gap-2">
            <Button
                v-for="option in quick"
                :key="option.label"
                type="button"
                size="sm"
                :variant="
                    same(props.borders, option.sides) ? 'default' : 'outline'
                "
                :aria-pressed="same(props.borders, option.sides)"
                @click="emit('update', { ...option.sides })"
            >
                {{ option.label }}
            </Button>
        </div>
        <div class="grid grid-cols-2 gap-x-4 gap-y-2">
            <Label
                v-for="side in sides"
                :key="side.key"
                class="flex min-h-8 items-center gap-2 font-normal"
            >
                <Checkbox
                    :model-value="props.borders[side.key]"
                    @update:model-value="
                        emit('update', {
                            ...props.borders,
                            [side.key]: $event === true,
                        })
                    "
                />
                {{ side.label }}
            </Label>
        </div>
        <p class="text-xs text-muted-foreground">
            Garis di antara dua sel hanya tampil kalau kedua sel menampilkannya.
        </p>
    </fieldset>
</template>
