<script setup lang="ts">
import { Search, X } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useListContext } from '@/composables/useListQuery';

defineProps<{ placeholder?: string }>();

const { search } = useListContext();
</script>

<template>
    <div class="relative w-full min-w-48 sm:max-w-sm">
        <Search
            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <Input
            v-model="search"
            type="search"
            class="pr-9 pl-9 [&::-webkit-search-cancel-button]:hidden"
            :placeholder="placeholder ?? 'Cari...'"
            aria-label="Cari"
        />
        <Button
            v-if="search"
            variant="ghost"
            size="icon"
            class="absolute top-1/2 right-1 size-7 -translate-y-1/2"
            aria-label="Hapus pencarian"
            @click="search = ''"
        >
            <X class="size-4" />
        </Button>
    </div>
</template>
