<script setup lang="ts">
import { SearchX } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useListContext } from '@/composables/useListQuery';

defineProps<{ filtered: boolean; emptyText?: string }>();

const { clearAll } = useListContext();
</script>

<template>
    <div
        class="flex flex-col items-center gap-3 rounded-xl border border-dashed py-14 text-center"
    >
        <SearchX class="size-8 text-muted-foreground" />
        <p class="text-muted-foreground">
            {{
                filtered
                    ? 'Tidak ada data yang cocok dengan pencarian atau filter Anda.'
                    : (emptyText ?? 'Belum ada data.')
            }}
        </p>
        <Button v-if="filtered" variant="outline" @click="clearAll">
            Hapus pencarian dan filter
        </Button>
    </div>
</template>
