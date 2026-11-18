<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import FilterField from '@/components/list/FilterField.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { isEmptyFilter, useListContext } from '@/composables/useListQuery';
import type { ListFilter } from '@/types';

defineProps<{ filter: ListFilter }>();

const { setFilter } = useListContext();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" class="gap-2">
                {{ filter.label }}
                <Badge v-if="!isEmptyFilter(filter.value)" variant="secondary">
                    {{
                        Array.isArray(filter.value) ? filter.value.length : '1'
                    }}
                </Badge>
                <ChevronDown class="size-4 text-muted-foreground" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="w-64 p-3">
            <FilterField
                :filter="filter"
                @change="setFilter(filter.key, $event)"
            />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
