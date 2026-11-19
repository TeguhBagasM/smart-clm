<script setup lang="ts">
import { Check } from '@lucide/vue';
import { safeColor } from '@/components/documents/config/header/color/colorMath';
import { cn } from '@/lib/utils';

defineProps<{ colors: string[]; current: string | null; label: string }>();
defineEmits<{ pick: [hex: string] }>();
</script>

<template>
    <div class="grid grid-cols-8 gap-1.5" role="group" :aria-label="label">
        <button
            v-for="hex in colors"
            :key="hex"
            type="button"
            :title="hex"
            :aria-label="hex"
            :aria-pressed="current?.toUpperCase() === hex.toUpperCase()"
            :style="{ backgroundColor: safeColor(hex) }"
            :class="
                cn(
                    'flex size-7 items-center justify-center rounded-md border border-input focus-visible:ring-[3px] focus-visible:ring-ring/70 focus-visible:outline-hidden',
                    current?.toUpperCase() === hex.toUpperCase() &&
                        'ring-2 ring-primary ring-offset-1',
                )
            "
            @click="$emit('pick', hex)"
        >
            <Check
                v-if="current?.toUpperCase() === hex.toUpperCase()"
                class="size-4 mix-blend-difference"
            />
        </button>
    </div>
</template>
