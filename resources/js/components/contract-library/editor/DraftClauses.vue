<script setup lang="ts">
import { ArrowDown, ArrowUp, Trash2 } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { moveTo } from '@/lib/contractLibrary';
import type { DraftState } from '@/lib/contractLibrary';

/**
 * The clauses of the draft in order. Move a clause with its buttons (the same on a
 * keyboard and a phone) or take it out. Saving sends the whole order.
 */
const clauses = defineModel<DraftState['clauses']>({ required: true });

defineProps<{ labels: Record<string, string> }>();

function move(from: number, to: number): void {
    clauses.value = moveTo(clauses.value, from, to);
}

function remove(index: number): void {
    clauses.value = clauses.value.filter((_, position) => position !== index);
}
</script>

<template>
    <section class="flex flex-col gap-3" :aria-label="labels.clauses">
        <h3 class="text-sm font-semibold">{{ labels.clauses }}</h3>
        <p
            v-if="clauses.length === 0"
            class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground"
        >
            {{ labels.clauses_empty }}
        </p>
        <ol class="flex flex-col gap-2">
            <li
                v-for="(clause, index) in clauses"
                :key="clause.libraryId"
                class="flex flex-col gap-2 rounded-lg border p-3 md:flex-row md:items-center"
            >
                <div class="flex min-w-0 flex-1 flex-col gap-1">
                    <span class="flex items-center gap-2">
                        <span class="text-sm text-muted-foreground"
                            >{{ index + 1 }}.</span
                        >
                        <span class="truncate font-medium">{{
                            clause.title
                        }}</span>
                    </span>
                    <Badge
                        v-if="clause.sourceUpdated"
                        variant="outline"
                        class="w-fit text-warning-foreground"
                        >{{ labels.clause_updated }}</Badge
                    >
                </div>
                <div class="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        class="size-11 md:size-9"
                        :disabled="index === 0"
                        :aria-label="`${labels.clause_up}: ${clause.title}`"
                        @click="move(index, index - 1)"
                    >
                        <ArrowUp />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        class="size-11 md:size-9"
                        :disabled="index === clauses.length - 1"
                        :aria-label="`${labels.clause_down}: ${clause.title}`"
                        @click="move(index, index + 1)"
                    >
                        <ArrowDown />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        class="h-11 md:h-9"
                        :aria-label="`${labels.clause_remove}: ${clause.title}`"
                        @click="remove(index)"
                    >
                        <Trash2 />{{ labels.clause_remove }}
                    </Button>
                </div>
            </li>
        </ol>
    </section>
</template>
