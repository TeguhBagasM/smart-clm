<script setup lang="ts">
import { SlidersHorizontal } from '@lucide/vue';
import FilterField from '@/components/list/FilterField.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useListContext } from '@/composables/useListQuery';
import type { ListMeta } from '@/types';

defineProps<{ list: ListMeta }>();

const { setFilter, clearAll } = useListContext();
</script>

<template>
    <Sheet>
        <SheetTrigger as-child>
            <Button variant="outline" class="gap-2">
                <SlidersHorizontal class="size-4" />
                Semua filter
                <Badge v-if="list.active_count > 0" variant="secondary">
                    {{ list.active_count }}
                </Badge>
            </Button>
        </SheetTrigger>
        <SheetContent class="flex w-full flex-col gap-0 sm:max-w-md">
            <SheetHeader>
                <SheetTitle>Filter</SheetTitle>
                <SheetDescription>
                    Hasil langsung diperbarui saat Anda memilih.
                </SheetDescription>
            </SheetHeader>
            <div class="flex flex-1 flex-col gap-5 overflow-y-auto px-4 pb-4">
                <div
                    v-for="filter in list.filters"
                    :key="filter.key"
                    class="flex flex-col gap-2"
                >
                    <Label class="font-medium">{{ filter.label }}</Label>
                    <FilterField
                        :filter="filter"
                        @change="setFilter(filter.key, $event)"
                    />
                </div>
            </div>
            <SheetFooter>
                <Button
                    variant="outline"
                    :disabled="list.active_count === 0"
                    @click="clearAll"
                >
                    Hapus semua filter
                </Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
