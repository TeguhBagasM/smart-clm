<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

defineProps<{
    cancelHref: NonNullable<InertiaLinkProps['href']>;
    processing: boolean;
    submitLabel: string;
}>();
</script>

<template>
    <div
        class="sticky bottom-0 z-10 -mx-4 flex flex-col-reverse gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur sm:flex-row sm:justify-end md:-mx-6 md:px-6"
    >
        <Button variant="outline" as-child class="sm:w-auto">
            <Link :href="cancelHref">Batal</Link>
        </Button>
        <Button type="submit" :disabled="processing" class="sm:w-auto">
            <Spinner v-if="processing" />
            {{ submitLabel }}
        </Button>
    </div>
</template>
