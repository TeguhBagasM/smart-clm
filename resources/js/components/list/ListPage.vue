<script setup lang="ts">
import ActiveFilterChips from '@/components/list/ActiveFilterChips.vue';
import ListEmpty from '@/components/list/ListEmpty.vue';
import ListPagination from '@/components/list/ListPagination.vue';
import ListToolbar from '@/components/list/ListToolbar.vue';
import { provideListQuery, useListQuery } from '@/composables/useListQuery';
import type { ListMeta, Paginated } from '@/types';

const props = defineProps<{
    list: ListMeta;
    paginated: Paginated<unknown>;
    /** Props the server should send again when the list changes. */
    only: string[];
    searchPlaceholder?: string;
    emptyText?: string;
}>();

const query = useListQuery(() => props.list, props.only);
provideListQuery(query);
</script>

<template>
    <div class="flex flex-col gap-4">
        <ListToolbar :list="list" :search-placeholder="searchPlaceholder" />
        <ActiveFilterChips :list="list" />
        <div
            :class="[
                'flex flex-col gap-4 transition-opacity',
                query.loading.value && 'opacity-60',
            ]"
            :aria-busy="query.loading.value"
        >
            <slot v-if="paginated.data.length > 0" />
            <ListEmpty
                v-else
                :filtered="list.active_count > 0 || list.search !== ''"
                :empty-text="emptyText"
            />
            <ListPagination :meta="paginated.meta" :list="list" />
        </div>
    </div>
</template>
