<script setup lang="ts">
import { safeColor } from '@/components/documents/config/header/color/colorMath';
import type { Box } from '@/lib/headerGeometry';

defineProps<{
    area: Box;
    image: Box | null;
    url: string | null;
    border: string;
    selected: boolean;
}>();
defineEmits<{ select: []; hover: [] }>();
</script>

<template>
    <g
        class="group cursor-pointer outline-hidden"
        tabindex="0"
        role="button"
        aria-label="Logo"
        :aria-pressed="selected"
        @click.stop="$emit('select')"
        @pointerenter="$emit('hover')"
        @keydown.enter.space.prevent="$emit('select')"
    >
        <rect
            :x="area.x"
            :y="area.y"
            :width="area.w"
            :height="area.h"
            fill="none"
            :stroke="safeColor(border)"
            stroke-width="0.5"
        />
        <image
            v-if="url && image"
            :href="url"
            :x="image.x"
            :y="image.y"
            :width="image.w"
            :height="image.h"
            preserveAspectRatio="xMidYMid meet"
        />
        <text
            v-else
            :x="area.x + area.w / 2"
            :y="area.y + area.h / 2"
            text-anchor="middle"
            dominant-baseline="central"
            font-size="9"
            class="fill-muted-foreground"
        >
            Logo belum diunggah
        </text>
        <rect
            :x="area.x"
            :y="area.y"
            :width="area.w"
            :height="area.h"
            fill="none"
            stroke-width="2"
            :class="
                selected
                    ? 'fill-primary/10 stroke-primary'
                    : 'stroke-transparent group-hover:stroke-primary/50 group-focus-visible:stroke-ring'
            "
            pointer-events="none"
        />
    </g>
</template>
