<script setup lang="ts">
import FilterDropdown from '@/components/list/FilterDropdown.vue';
import FilterSheet from '@/components/list/FilterSheet.vue';
import ListSearch from '@/components/list/ListSearch.vue';
import ListSort from '@/components/list/ListSort.vue';
import type { ListMeta } from '@/types';

defineProps<{ list: ListMeta; searchPlaceholder?: string }>();
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <ListSearch :placeholder="searchPlaceholder" />
            <ListSort :list="list" />
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="hidden flex-wrap items-center gap-2 md:flex">
                <FilterDropdown
                    v-for="filter in list.filters.filter(
                        (item) => item.primary,
                    )"
                    :key="filter.key"
                    :filter="filter"
                />
            </div>
            <FilterSheet :list="list" />
        </div>
    </div>
</template>
