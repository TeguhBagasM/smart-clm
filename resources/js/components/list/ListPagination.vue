<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useListContext } from '@/composables/useListQuery';
import type { ListMeta, Paginated } from '@/types';

const props = defineProps<{
    meta: Paginated<unknown>['meta'];
    list: ListMeta;
}>();

const { goToPage, setPerPage } = useListContext();

/** Page numbers around the current page, with gaps shown as null. */
const pages = computed<(number | null)[]>(() => {
    const { current_page: current, last_page: last } = props.meta;
    const wanted = new Set(
        [1, last, current - 1, current, current + 1].filter(
            (page) => page >= 1 && page <= last,
        ),
    );
    const sorted = [...wanted].sort((a, b) => a - b);

    return sorted.flatMap((page, index) =>
        index > 0 && page - sorted[index - 1] > 1 ? [null, page] : [page],
    );
});
</script>

<template>
    <nav
        class="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground"
        aria-label="Paginasi"
    >
        <div class="flex items-center gap-3">
            <span v-if="meta.total > 0">
                {{ meta.from }}–{{ meta.to }} dari {{ meta.total }}
            </span>
            <Select
                :model-value="String(list.per_page)"
                @update:model-value="setPerPage(Number($event))"
            >
                <SelectTrigger class="w-32" aria-label="Jumlah per halaman">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="size in list.per_page_options"
                        :key="size"
                        :value="String(size)"
                    >
                        {{ size }} / halaman
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>
        <div v-if="meta.last_page > 1" class="flex items-center gap-1">
            <Button
                variant="outline"
                size="icon"
                aria-label="Halaman sebelumnya"
                :disabled="meta.current_page <= 1"
                @click="goToPage(meta.current_page - 1)"
            >
                <ChevronLeft />
            </Button>
            <template v-for="(page, index) in pages" :key="index">
                <span v-if="page === null" class="px-1">…</span>
                <Button
                    v-else
                    :variant="page === meta.current_page ? 'default' : 'ghost'"
                    size="icon"
                    :aria-current="
                        page === meta.current_page ? 'page' : undefined
                    "
                    :aria-label="`Halaman ${page}`"
                    @click="goToPage(page)"
                >
                    {{ page }}
                </Button>
            </template>
            <Button
                variant="outline"
                size="icon"
                aria-label="Halaman berikutnya"
                :disabled="meta.current_page >= meta.last_page"
                @click="goToPage(meta.current_page + 1)"
            >
                <ChevronRight />
            </Button>
        </div>
    </nav>
</template>
