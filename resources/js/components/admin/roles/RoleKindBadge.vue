<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

defineProps<{
    isSystem: boolean;
}>();
</script>

<template>
    <Badge v-if="isSystem" variant="secondary">Sistem</Badge>
    <Badge v-else variant="outline">Kustom</Badge>
</template>
