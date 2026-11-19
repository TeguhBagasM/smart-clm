<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
    gradient,
    hexToHueSat,
    hslToHex,
    normalizeHex,
    type Hsl,
} from '@/components/documents/config/header/color/colorMath';
import { Input } from '@/components/ui/input';

const props = defineProps<{ hex: string | null }>();
const emit = defineEmits<{ pick: [hex: string] }>();

const hsl = ref<Hsl>(hexToHueSat(props.hex ?? ''));
const typed = ref(props.hex ?? '');

watch(
    () => props.hex,
    (hex) => {
        typed.value = hex ?? '';
        if (hex && hslToHex(hsl.value) !== hex.slice(0, 7).toUpperCase()) {
            hsl.value = hexToHueSat(hex);
        }
    },
);

const hueTrack = computed(() =>
    gradient(
        [0, 60, 120, 180, 240, 300, 360].map((h) =>
            hslToHex({ h: h % 360, s: 100, l: 50 }),
        ),
    ),
);
const satTrack = computed(() =>
    gradient([0, 100].map((s) => hslToHex({ ...hsl.value, s }))),
);
const lightTrack = computed(() =>
    gradient([0, 50, 100].map((l) => hslToHex({ ...hsl.value, l }))),
);

const sliders = [
    { key: 'h', label: 'Warna (hue)', max: 360, track: hueTrack },
    { key: 's', label: 'Kepekatan', max: 100, track: satTrack },
    { key: 'l', label: 'Terang', max: 100, track: lightTrack },
] as const;

function slide(key: keyof Hsl, value: number): void {
    hsl.value = { ...hsl.value, [key]: value };
    emit('pick', hslToHex(hsl.value));
}

function commit(): void {
    const hex = normalizeHex(typed.value);

    if (hex) emit('pick', hex);
    else typed.value = props.hex ?? '';
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <label
            v-for="slider in sliders"
            :key="slider.key"
            class="flex flex-col gap-0.5 text-xs text-muted-foreground"
        >
            {{ slider.label }}
            <input
                type="range"
                min="0"
                :max="slider.max"
                :value="hsl[slider.key]"
                :aria-label="slider.label"
                class="h-3 w-full cursor-pointer appearance-none rounded-full border border-input"
                :style="{ backgroundImage: slider.track.value }"
                @input="
                    slide(
                        slider.key,
                        Number(($event.target as HTMLInputElement).value),
                    )
                "
            />
        </label>
        <label class="flex items-center gap-2 text-xs text-muted-foreground">
            Kode hex
            <Input
                v-model="typed"
                class="h-8 w-32 font-mono uppercase"
                maxlength="9"
                placeholder="#RRGGBB"
                @change="commit"
                @keydown.enter.prevent="commit"
            />
        </label>
    </div>
</template>
