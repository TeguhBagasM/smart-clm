<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

/** The parties of the template, in order. Each label is what the contract calls the party. */
const parties = defineModel<string[]>({ required: true });

defineProps<{ labels: Record<string, string> }>();

function add(): void {
    parties.value = [...parties.value, ''];
}

function remove(index: number): void {
    parties.value = parties.value.filter((_, position) => position !== index);
}

function update(index: number, label: string): void {
    parties.value = parties.value.map((value, position) =>
        position === index ? label : value,
    );
}
</script>

<template>
    <section class="flex flex-col gap-3" :aria-label="labels.parties">
        <h3 class="text-sm font-semibold">{{ labels.parties }}</h3>
        <p v-if="parties.length === 0" class="text-sm text-muted-foreground">
            {{ labels.party_empty }}
        </p>
        <ol class="flex flex-col gap-2">
            <li
                v-for="(label, index) in parties"
                :key="index"
                class="flex items-center gap-2"
            >
                <span
                    class="w-6 shrink-0 text-center text-sm text-muted-foreground"
                    >{{ index + 1 }}</span
                >
                <Input
                    :model-value="label"
                    :aria-label="`${labels.parties} ${index + 1}`"
                    class="h-11 md:h-9"
                    @update:model-value="update(index, String($event))"
                />
                <Button
                    type="button"
                    variant="ghost"
                    class="size-11 shrink-0 md:size-9"
                    :aria-label="`${labels.party_remove} ${index + 1}`"
                    @click="remove(index)"
                >
                    <Trash2 />
                </Button>
            </li>
        </ol>
        <Button
            type="button"
            variant="outline"
            class="h-11 w-full md:h-9 md:w-auto"
            @click="add"
        >
            <Plus />{{ labels.party_add }}
        </Button>
    </section>
</template>
