<script setup lang="ts">
import { Ban, SlidersHorizontal, Wand2 } from '@lucide/vue';
import { computed, inject } from 'vue';
import ColorFreePicker from '@/components/documents/config/header/color/ColorFreePicker.vue';
import ColorSwatchGrid from '@/components/documents/config/header/color/ColorSwatchGrid.vue';
import { safeColor } from '@/components/documents/config/header/color/colorMath';
import { useBrandColors } from '@/components/documents/config/header/color/useBrandColors';
import { useRecentColors } from '@/components/documents/config/header/color/useRecentColors';
import { headerContextKey } from '@/components/documents/config/header/headerContext';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

const model = defineModel<string | null>({ required: true });

defineProps<{ label: string; allowNone?: boolean; allowAuto?: boolean }>();

const context = inject(headerContextKey);
const { recent, remember } = useRecentColors();
const brand = useBrandColors();

const swatches = computed(() => context?.palette.swatches ?? []);
const isHex = computed(() => safeColor(model.value) !== undefined);
const summary = computed(() =>
    model.value === null
        ? 'Tanpa warna'
        : model.value === 'auto'
          ? 'Otomatis'
          : model.value,
);

function pick(value: string | null): void {
    model.value = value;
}

function closed(open: boolean): void {
    if (!open && model.value && isHex.value) remember(model.value);
}
</script>

<template>
    <Popover @update:open="closed">
        <PopoverTrigger as-child>
            <Button
                type="button"
                variant="outline"
                size="sm"
                class="justify-start gap-2"
                :aria-label="`${label}: ${summary}`"
            >
                <span
                    class="flex size-4 items-center justify-center rounded-sm border border-input"
                    :style="{ backgroundColor: safeColor(model) }"
                >
                    <Ban v-if="model === null" class="size-3" />
                    <Wand2 v-else-if="model === 'auto'" class="size-3" />
                </span>
                <span class="text-xs">{{ label }}</span>
            </Button>
        </PopoverTrigger>
        <PopoverContent class="flex flex-col gap-3">
            <div class="flex flex-wrap gap-1.5">
                <Button
                    v-if="allowNone"
                    type="button"
                    size="sm"
                    :variant="model === null ? 'default' : 'outline'"
                    @click="pick(null)"
                >
                    <Ban /> Tanpa warna
                </Button>
                <Button
                    v-if="allowAuto"
                    type="button"
                    size="sm"
                    :variant="model === 'auto' ? 'default' : 'outline'"
                    @click="pick('auto')"
                >
                    <Wand2 /> Otomatis
                </Button>
            </div>
            <ColorSwatchGrid
                label="Warna umum"
                :colors="swatches"
                :current="model"
                @pick="pick"
            />
            <section v-if="recent.length">
                <h4 class="mb-1 text-xs font-medium text-muted-foreground">
                    Baru dipakai
                </h4>
                <ColorSwatchGrid
                    label="Baru dipakai"
                    :colors="recent"
                    :current="model"
                    @pick="pick"
                />
            </section>
            <section>
                <h4 class="mb-1 text-xs font-medium text-muted-foreground">
                    Warna perusahaan
                </h4>
                <ColorSwatchGrid
                    v-if="brand.colors?.value.length"
                    label="Warna perusahaan"
                    :colors="brand.colors.value.map((c) => c.hex)"
                    :current="model"
                    @pick="pick"
                />
                <p v-else class="text-xs text-muted-foreground">
                    Belum ada. Pilih warna lalu simpan di sini.
                </p>
                <Button
                    v-if="isHex && model && !brand.has(model) && !brand.full()"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="mt-1"
                    @click="brand.add(model)"
                >
                    Simpan {{ model }} sebagai warna perusahaan
                </Button>
                <Button
                    v-else-if="isHex && model && brand.has(model)"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="mt-1"
                    @click="brand.remove(model)"
                >
                    Hapus dari warna perusahaan
                </Button>
            </section>
            <section>
                <h4
                    class="mb-1 flex items-center gap-1 text-xs font-medium text-muted-foreground"
                >
                    <SlidersHorizontal class="size-3.5" /> Warna bebas
                </h4>
                <ColorFreePicker :hex="isHex ? model : null" @pick="pick" />
            </section>
        </PopoverContent>
    </Popover>
</template>
