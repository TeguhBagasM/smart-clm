<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

defineProps<{ active: boolean }>();
</script>

<template>
    <Badge v-if="active" variant="secondary" class="gap-1.5">
        <span class="size-1.5 rounded-full bg-success" />
        Aktif
    </Badge>
    <Badge v-else variant="outline" class="gap-1.5 text-muted-foreground">
        <span class="size-1.5 rounded-full bg-muted-foreground" />
        Nonaktif
    </Badge>
</template>
