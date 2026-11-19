<script setup lang="ts">
import { LayoutTemplate } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { HeaderPreset } from '@/types';

defineProps<{ presets: HeaderPreset[] }>();
defineEmits<{ apply: [preset: HeaderPreset] }>();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button type="button" variant="outline" size="sm"
                ><LayoutTemplate /> Preset</Button
            >
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-72">
            <DropdownMenuItem
                v-for="p in presets"
                :key="p.key"
                class="flex-col items-start gap-0.5"
                @select="$emit('apply', p)"
            >
                <span class="font-medium">{{ p.name }}</span>
                <span class="text-xs text-muted-foreground">{{
                    p.description
                }}</span>
            </DropdownMenuItem>
            <p class="px-2 py-1.5 text-xs text-muted-foreground">
                Mengganti seluruh kop. Bisa dibatalkan dengan Undo.
            </p>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
