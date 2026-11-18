<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

defineProps<{ title: string; count?: number }>();
</script>

<template>
    <Card class="gap-4 py-4">
        <CardHeader
            class="flex flex-row items-center justify-between gap-2 px-4"
        >
            <CardTitle class="text-base">
                {{ title }}
                <span
                    v-if="count !== undefined"
                    class="ml-1 font-normal text-muted-foreground"
                >
                    ({{ count }})
                </span>
            </CardTitle>
            <slot name="action" />
        </CardHeader>
        <CardContent class="px-4">
            <slot />
        </CardContent>
    </Card>
</template>
