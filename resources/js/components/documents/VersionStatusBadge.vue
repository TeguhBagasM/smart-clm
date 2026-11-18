<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { VersionStatus } from '@/types';

defineProps<{ status: VersionStatus; label: string }>();

/** The dot only repeats what the label says; it is never the sole signal. */
const dot: Record<VersionStatus, string> = {
    draft: 'bg-muted-foreground',
    under_review: 'bg-warning',
    approved: 'bg-primary',
    effective: 'bg-success',
    superseded: 'bg-destructive',
};
</script>

<template>
    <Badge variant="secondary" class="gap-1.5">
        <span :class="['size-1.5 rounded-full', dot[status]]" />
        {{ label }}
    </Badge>
</template>
