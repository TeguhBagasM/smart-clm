<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { ComparisonStatusValue } from '@/types';

defineProps<{ status: ComparisonStatusValue; label: string }>();

const dot: Record<ComparisonStatusValue, string> = {
    queued: 'bg-muted-foreground',
    processing: 'bg-primary',
    completed: 'bg-success',
    failed: 'bg-destructive',
};
</script>

<template>
    <Badge variant="secondary" class="gap-1.5">
        <span :class="['size-1.5 rounded-full', dot[status]]" />
        {{ label }}
    </Badge>
</template>
