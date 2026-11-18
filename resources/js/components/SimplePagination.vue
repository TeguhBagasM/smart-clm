<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

defineProps<{
    links: Paginated<unknown>['links'];
    meta: Paginated<unknown>['meta'];
}>();
</script>

<template>
    <nav
        v-if="meta.last_page > 1"
        class="flex items-center justify-between gap-4 text-sm text-muted-foreground"
        aria-label="Paginasi"
    >
        <span>{{ meta.from }}–{{ meta.to }} dari {{ meta.total }}</span>
        <div class="flex gap-2">
            <Button
                variant="outline"
                size="sm"
                :disabled="!links.prev"
                as-child
            >
                <Link v-if="links.prev" :href="links.prev" preserve-scroll>
                    <ChevronLeft /> Sebelumnya
                </Link>
                <span v-else><ChevronLeft /> Sebelumnya</span>
            </Button>
            <Button
                variant="outline"
                size="sm"
                :disabled="!links.next"
                as-child
            >
                <Link v-if="links.next" :href="links.next" preserve-scroll>
                    Berikutnya <ChevronRight />
                </Link>
                <span v-else>Berikutnya <ChevronRight /></span>
            </Button>
        </div>
    </nav>
</template>
