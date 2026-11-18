<script setup lang="ts" generic="T extends object">
import { ArrowDown, ArrowUp, ChevronsUpDown } from '@lucide/vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useListContext } from '@/composables/useListQuery';
import type { ListColumn, ListMeta } from '@/types';

defineProps<{
    columns: ListColumn[];
    rows: T[];
    rowKey: (row: T) => string;
    list: ListMeta;
}>();

const { toggleSort } = useListContext();

function cell(row: T, key: string): unknown {
    return (row as Record<string, unknown>)[key];
}
</script>

<template>
    <div class="rounded-xl border">
        <Table>
            <TableHeader>
                <TableRow class="hover:bg-transparent">
                    <TableHead
                        v-for="column in columns"
                        :key="column.key"
                        :class="column.class"
                        :aria-sort="
                            column.sort && column.sort === list.sort
                                ? list.direction === 'asc'
                                    ? 'ascending'
                                    : 'descending'
                                : undefined
                        "
                    >
                        <button
                            v-if="column.sort"
                            type="button"
                            class="inline-flex items-center gap-1 font-medium hover:text-foreground"
                            @click="toggleSort(column.sort)"
                        >
                            {{ column.label }}
                            <template v-if="column.sort === list.sort">
                                <ArrowUp
                                    v-if="list.direction === 'asc'"
                                    class="size-3.5"
                                />
                                <ArrowDown v-else class="size-3.5" />
                            </template>
                            <ChevronsUpDown
                                v-else
                                class="size-3.5 text-muted-foreground"
                            />
                        </button>
                        <template v-else>{{ column.label }}</template>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="row in rows" :key="rowKey(row)">
                    <TableCell
                        v-for="column in columns"
                        :key="column.key"
                        :class="column.class"
                    >
                        <slot :name="`cell-${column.key}`" :row="row">
                            {{ cell(row, column.key) }}
                        </slot>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
