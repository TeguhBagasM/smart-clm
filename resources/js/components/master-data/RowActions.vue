<script setup lang="ts">
import { Eye, EyeOff } from '@lucide/vue';
import { Button } from '@/components/ui/button';

/** Hide or show a row (the change sheet goes in the slot). Nothing is ever deleted. */
defineProps<{
    active: boolean;
    canToggle: boolean;
    busy: boolean;
}>();

defineEmits<{ toggle: [] }>();
</script>

<template>
    <div
        class="flex flex-col gap-1 md:flex-row md:flex-wrap md:items-center md:justify-end"
    >
        <slot />
        <Button
            v-if="canToggle"
            variant="ghost"
            class="h-11 w-full md:h-9 md:w-auto"
            :disabled="busy"
            @click="$emit('toggle')"
        >
            <component :is="active ? EyeOff : Eye" />
            {{ active ? 'Sembunyikan' : 'Tampilkan lagi' }}
        </Button>
    </div>
</template>
