<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { ChangeKindValue } from '@/types';

defineProps<{ kind: ChangeKindValue; label: string }>();

/** The dot repeats the label; the label is always the signal. */
const dot: Record<ChangeKindValue, string> = {
    added: 'bg-success',
    removed: 'bg-destructive',
    changed: 'bg-warning',
    unchanged: 'bg-muted-foreground',
};
</script>

<template>
    <Badge variant="secondary" class="gap-1.5">
        <span :class="['size-1.5 rounded-full', dot[kind]]" />
        {{ label }}
    </Badge>
</template>
