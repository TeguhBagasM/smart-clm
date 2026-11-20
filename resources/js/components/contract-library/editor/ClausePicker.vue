<script setup lang="ts">
import { Plus, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { LibraryClauseOption } from '@/types';

/**
 * The clause library beside the draft: search it and insert a clause. A clause
 * already in the draft cannot be inserted twice; the server would refuse it.
 */
const props = defineProps<{
    options: LibraryClauseOption[];
    usedIds: number[];
    labels: Record<string, string>;
}>();

const emit = defineEmits<{ add: [option: LibraryClauseOption] }>();

const search = ref('');

const matches = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.options.filter(
        (option) =>
            term === '' ||
            option.label.toLowerCase().includes(term) ||
            (option.category ?? '').toLowerCase().includes(term),
    );
});
</script>

<template>
    <section class="flex flex-col gap-3" :aria-label="labels.picker">
        <h3 class="text-sm font-semibold">{{ labels.picker }}</h3>
        <div class="relative">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            <Input
                v-model="search"
                type="search"
                :placeholder="labels.picker_search"
                class="h-11 pl-9 md:h-9"
                :aria-label="labels.picker_search"
            />
        </div>
        <ul class="flex max-h-[28rem] flex-col gap-2 overflow-y-auto pr-1">
            <li
                v-for="option in matches"
                :key="option.value"
                class="flex items-center justify-between gap-2 rounded-lg border p-3"
            >
                <span class="min-w-0">
                    <span class="block truncate text-sm font-medium">{{
                        option.label
                    }}</span>
                    <span class="block text-xs text-muted-foreground"
                        >{{ option.category ?? '—' }} · Revisi
                        {{ option.revision }}</span
                    >
                </span>
                <Button
                    v-if="!usedIds.includes(option.value)"
                    type="button"
                    variant="secondary"
                    class="h-11 shrink-0 md:h-9"
                    :aria-label="`${labels.picker_insert}: ${option.label}`"
                    @click="emit('add', option)"
                >
                    <Plus />{{ labels.picker_insert }}
                </Button>
                <span v-else class="shrink-0 text-xs text-muted-foreground">{{
                    labels.picker_used
                }}</span>
            </li>
            <li
                v-if="matches.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ labels.picker_empty }}
            </li>
        </ul>
    </section>
</template>
