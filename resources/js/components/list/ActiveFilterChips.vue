<script setup lang="ts">
import { X } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { isEmptyFilter, useListContext } from '@/composables/useListQuery';
import type { DateRangeValue, ListFilter, ListMeta } from '@/types';

defineProps<{ list: ListMeta }>();

const { clearFilter, clearAll } = useListContext();

const day = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' });

function formatDay(value: string | null): string {
    return value ? day.format(new Date(`${value}T00:00:00`)) : '…';
}

function describe(filter: ListFilter): string {
    const value = filter.value;
    const label = (item: string): string =>
        filter.options.find((option) => option.value === item)?.label ?? item;

    if (typeof value === 'boolean') return value ? 'Ya' : 'Tidak';
    if (Array.isArray(value)) {
        const names = value.map(label);
        return names.length > 2
            ? `${names.slice(0, 2).join(', ')} +${names.length - 2}`
            : names.join(', ');
    }
    if (value && typeof value === 'object') {
        const range = value as DateRangeValue;
        return `${formatDay(range.from)} – ${formatDay(range.to)}`;
    }

    return value ? label(value) : '';
}
</script>

<template>
    <div
        v-if="list.active_count > 0"
        class="flex flex-wrap items-center gap-2"
        aria-label="Filter aktif"
    >
        <template v-for="filter in list.filters" :key="filter.key">
            <Badge
                v-if="!isEmptyFilter(filter.value)"
                variant="secondary"
                class="gap-1 py-1 pr-1 pl-2.5 text-sm font-normal"
            >
                <span class="text-muted-foreground">{{ filter.label }}:</span>
                {{ describe(filter) }}
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-5"
                    :aria-label="`Hapus filter ${filter.label}`"
                    @click="clearFilter(filter.key)"
                >
                    <X class="size-3" />
                </Button>
            </Badge>
        </template>
        <Button variant="link" size="sm" @click="clearAll">
            Hapus semua
        </Button>
    </div>
</template>
