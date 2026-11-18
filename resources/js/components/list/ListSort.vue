<script setup lang="ts">
import { ArrowDownNarrowWide, ArrowUpNarrowWide } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useListContext } from '@/composables/useListQuery';
import type { ListMeta } from '@/types';

defineProps<{ list: ListMeta }>();

const { setSort, setDirection } = useListContext();
</script>

<template>
    <div class="flex w-full items-center gap-1 sm:w-auto">
        <Select
            :model-value="list.sort"
            @update:model-value="setSort(String($event))"
        >
            <SelectTrigger
                class="w-full sm:w-52"
                aria-label="Urutkan berdasarkan"
            >
                <SelectValue placeholder="Urutkan" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="sort in list.sorts"
                    :key="sort.key"
                    :value="sort.key"
                >
                    {{ sort.label }}
                </SelectItem>
            </SelectContent>
        </Select>
        <Button
            variant="outline"
            size="icon"
            :aria-label="
                list.direction === 'asc'
                    ? 'Urutan naik, klik untuk menurun'
                    : 'Urutan menurun, klik untuk naik'
            "
            @click="setDirection(list.direction === 'asc' ? 'desc' : 'asc')"
        >
            <ArrowUpNarrowWide v-if="list.direction === 'asc'" />
            <ArrowDownNarrowWide v-else />
        </Button>
    </div>
</template>
