<script setup lang="ts">
import { Check, ChevronsUpDown } from '@lucide/vue';
import { computed, nextTick, ref, useId } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export type SearchOption = {
    value: string;
    label: string;
    description?: string;
};

/**
 * A dropdown to pick one thing. When the list is long (more than `searchFrom`
 * options) it has a search box on top that filters by label and description;
 * arrow keys move through the matches and Enter picks the first one.
 * `name` makes it work inside a plain form, like a select.
 */
const model = defineModel<string | null>({ required: true });

const props = withDefaults(
    defineProps<{
        options: SearchOption[];
        label: string;
        placeholder?: string;
        searchPlaceholder?: string;
        searchFrom?: number;
        name?: string;
        id?: string;
        disabled?: boolean;
    }>(),
    { placeholder: 'Pilih…', searchPlaceholder: 'Cari…', searchFrom: 7 },
);

const generated = useId();
const open = ref(false);
const query = ref('');
const list = ref<HTMLElement | null>(null);

const searchable = computed(() => props.options.length > props.searchFrom);
const chosen = computed(() =>
    props.options.find((o) => o.value === model.value),
);
const shown = computed(() => {
    const q = query.value.trim().toLowerCase();

    return props.options
        .filter(
            (o) =>
                !q ||
                `${o.label} ${o.description ?? ''}`.toLowerCase().includes(q),
        )
        .slice(0, 100);
});

function pick(option: SearchOption): void {
    model.value = option.value;
    open.value = false;
    query.value = '';
}

function move(event: KeyboardEvent, from: number, step: number): void {
    event.preventDefault();

    const items = list.value?.querySelectorAll<HTMLElement>('button');
    const next = from + step;

    if (!items?.length) return;

    if (next < 0)
        return void (
            document.getElementById(`${generated}-search`) as HTMLElement | null
        )?.focus();

    items[Math.min(next, items.length - 1)]?.focus();
}

function firstOption(): void {
    void nextTick(() =>
        list.value?.querySelector<HTMLElement>('button')?.focus(),
    );
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                :id="id"
                type="button"
                variant="outline"
                role="combobox"
                :aria-expanded="open"
                :aria-label="label"
                :disabled="disabled"
                class="w-full min-w-0 justify-between font-normal"
            >
                <span v-if="chosen" class="truncate">
                    {{ chosen.label }}
                    <span
                        v-if="chosen.description"
                        class="text-muted-foreground"
                        >· {{ chosen.description }}</span
                    >
                </span>
                <span v-else class="truncate text-muted-foreground">{{
                    placeholder
                }}</span>
                <ChevronsUpDown class="size-4 shrink-0 opacity-50" />
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-(--reka-popover-trigger-width) min-w-64 p-2">
            <Input
                v-if="searchable"
                :id="`${generated}-search`"
                v-model="query"
                :placeholder="searchPlaceholder"
                :aria-label="searchPlaceholder"
                class="mb-2"
                @keydown.enter.prevent="shown[0] && pick(shown[0])"
                @keydown.down="firstOption"
            />
            <ul
                ref="list"
                role="listbox"
                :aria-label="label"
                class="max-h-60 overflow-y-auto"
            >
                <li
                    v-for="(o, index) in shown"
                    :key="o.value"
                    role="option"
                    :aria-selected="o.value === model"
                >
                    <button
                        type="button"
                        :class="
                            cn(
                                'flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent focus-visible:bg-accent focus-visible:outline-hidden',
                                o.value === model && 'bg-accent',
                            )
                        "
                        @click="pick(o)"
                        @keydown.down="move($event, index, 1)"
                        @keydown.up="move($event, index, -1)"
                    >
                        <Check
                            :class="
                                cn(
                                    'size-4 shrink-0',
                                    o.value === model
                                        ? 'opacity-100'
                                        : 'opacity-0',
                                )
                            "
                        />
                        <span class="flex min-w-0 flex-col">
                            <span class="truncate">{{ o.label }}</span>
                            <span
                                v-if="o.description"
                                class="truncate text-xs text-muted-foreground"
                                >{{ o.description }}</span
                            >
                        </span>
                    </button>
                </li>
                <li
                    v-if="shown.length === 0"
                    class="px-2 py-3 text-sm text-muted-foreground"
                >
                    Tidak ada yang cocok.
                </li>
            </ul>
        </PopoverContent>
    </Popover>
    <input v-if="name" type="hidden" :name="name" :value="model ?? ''" />
</template>
