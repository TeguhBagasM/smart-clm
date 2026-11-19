<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import { computed, inject } from 'vue';
import {
    parseContent,
    serializeContent,
    type Segment,
} from '@/components/documents/config/header/contentTokens';
import { headerContextKey } from '@/components/documents/config/header/headerContext';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

const model = defineModel<string>({ required: true });

const MAX = 200;
const context = inject(headerContextKey);

const segments = computed(() => parseContent(model.value));
const options = computed(() =>
    (context?.fields ?? []).filter((f) => f.value !== 'text'),
);
const labelOf = (value: string): string =>
    context?.fields.find((f) => f.value === value)?.label ?? value;

function commit(next: Segment[]): void {
    model.value = serializeContent(next).slice(0, MAX);
}

function edit(index: number, value: string): void {
    commit(segments.value.map((s, i) => (i === index ? { ...s, value } : s)));
}

function insert(field: string): void {
    commit([
        ...segments.value,
        { kind: 'field', value: field },
        { kind: 'text', value: '' },
    ]);
}

function removeField(index: number): void {
    commit(segments.value.filter((_, i) => i !== index));
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div
            class="flex min-h-10 flex-wrap items-center gap-1 rounded-md border border-input bg-background px-2 py-1 focus-within:ring-[3px] focus-within:ring-ring/70"
            role="group"
            aria-label="Isi sel"
        >
            <template v-for="(segment, index) in segments" :key="index">
                <span
                    v-if="segment.kind === 'field'"
                    class="inline-flex items-center gap-1 rounded-full bg-secondary px-2 py-0.5 text-xs text-secondary-foreground"
                >
                    {{ labelOf(segment.value) }}
                    <button
                        type="button"
                        class="rounded-full hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring/70 focus-visible:outline-hidden"
                        :aria-label="`Hapus isian ${labelOf(segment.value)}`"
                        @click="removeField(index)"
                    >
                        <X class="size-3" />
                    </button>
                </span>
                <input
                    v-else
                    :value="segment.value"
                    :size="Math.max(segment.value.length, 1)"
                    :aria-label="`Teks ${index / 2 + 1}`"
                    :placeholder="segments.length === 1 ? 'Ketik teks…' : ''"
                    class="min-w-2 bg-transparent text-sm outline-hidden"
                    @input="
                        edit(index, ($event.target as HTMLInputElement).value)
                    "
                />
            </template>
        </div>
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="self-start"
                    ><Plus /> Sisipkan field</Button
                >
            </DropdownMenuTrigger>
            <DropdownMenuContent class="max-h-72">
                <DropdownMenuItem
                    v-for="f in options"
                    :key="f.value"
                    @select="insert(f.value)"
                    >{{ f.label }}</DropdownMenuItem
                >
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
