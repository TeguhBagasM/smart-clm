<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HeaderRow } from '@/types';

defineProps<{ row: HeaderRow; samples: Record<string, string> }>();

const PX_PER_POINT = 1.5;
</script>

<template>
    <div class="flex" :style="{ height: `${row.height * PX_PER_POINT}px` }">
        <div
            v-for="(cell, index) in row.cells"
            :key="index"
            :style="{ flex: cell.span }"
            :class="
                cn(
                    'flex min-w-0 items-center border-r border-b border-foreground/40 px-2 text-xs last:border-r-0',
                    cell.fill === 'dark' &&
                        'bg-primary text-primary-foreground',
                    cell.fill === 'muted' && 'bg-muted',
                    cell.bold && 'font-semibold',
                    cell.italic && 'italic',
                    cell.uppercase && 'uppercase',
                    cell.align === 'center' && 'justify-center text-center',
                    cell.align === 'right' && 'justify-end text-right',
                )
            "
        >
            <span class="truncate">
                {{
                    cell.field === 'text'
                        ? cell.label
                        : `${cell.label ? cell.label + ' : ' : ''}${samples[cell.field] ?? ''}`
                }}
            </span>
        </div>
    </div>
</template>
