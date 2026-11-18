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
    <ul class="flex flex-col gap-3 md:hidden" aria-label="Daftar">
        <li
            v-for="row in rows"
            :key="rowKey(row)"
            class="flex flex-col gap-3 rounded-xl border p-4"
        >
            <div>
                <slot :name="`cell-${columns[0].key}`" :row="row">
                    {{ cell(row, columns[0].key) }}
                </slot>
            </div>
            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                <template v-for="column in columns.slice(1)" :key="column.key">
                    <template v-if="column.label">
                        <dt class="text-muted-foreground">
                            {{ column.label }}
                        </dt>
                        <dd>
                            <slot :name="`cell-${column.key}`" :row="row">
                                {{ cell(row, column.key) }}
                            </slot>
                        </dd>
                    </template>
                    <dd v-else class="col-span-2">
                        <slot :name="`cell-${column.key}`" :row="row" />
                    </dd>
                </template>
            </dl>
        </li>
    </ul>
    <div class="hidden rounded-xl border md:block">
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
