<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

/** Whether a row is offered in new choices, and whether it came with the system. */
defineProps<{ active: boolean; system?: boolean }>();
</script>

<template>
    <span class="inline-flex flex-wrap items-center gap-1.5">
        <Badge v-if="active" variant="secondary" class="gap-1.5">
            <span class="size-1.5 rounded-full bg-success" />
            Tampil di pilihan
        </Badge>
        <Badge v-else variant="outline" class="gap-1.5 text-muted-foreground">
            <span class="size-1.5 rounded-full bg-muted-foreground" />
            Disembunyikan
        </Badge>
        <Badge v-if="system" variant="outline">Bawaan sistem</Badge>
    </span>
</template>
