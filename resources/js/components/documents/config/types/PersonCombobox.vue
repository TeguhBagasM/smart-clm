<script setup lang="ts">
import { Check, ChevronsUpDown } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type { SignerOption } from '@/types';

/** Picks one person: type to search by name or position; each option shows name and position. */
const model = defineModel<string | null>({ required: true });
const props = defineProps<{ options: SignerOption[]; label: string }>();

const open = ref(false);
const query = ref('');
const chosen = computed(() =>
    props.options.find((o) => o.value === model.value),
);
const shown = computed(() => {
    const q = query.value.trim().toLowerCase();

    return props.options
        .filter((o) => !q || `${o.label} ${o.role}`.toLowerCase().includes(q))
        .slice(0, 50);
});

function pick(option: SignerOption): void {
    model.value = option.value;
    open.value = false;
    query.value = '';
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                type="button"
                variant="outline"
                role="combobox"
                :aria-expanded="open"
                :aria-label="label"
                class="w-full justify-between font-normal"
            >
                <span v-if="chosen" class="truncate"
                    >{{ chosen.label }}
                    <span class="text-muted-foreground"
                        >· {{ chosen.role || 'tanpa jabatan' }}</span
                    ></span
                >
                <span v-else class="text-muted-foreground">Pilih orang…</span>
                <ChevronsUpDown class="size-4 shrink-0 opacity-50" />
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-80 p-2">
            <Input
                v-model="query"
                placeholder="Cari nama atau jabatan…"
                aria-label="Cari orang"
                class="mb-2"
                @keydown.enter.prevent="shown[0] && pick(shown[0])"
            />
            <ul
                role="listbox"
                :aria-label="label"
                class="max-h-60 overflow-y-auto"
            >
                <li
                    v-for="o in shown"
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
                                class="truncate text-xs text-muted-foreground"
                                >{{ o.role || 'tanpa jabatan' }}</span
                            >
                        </span>
                    </button>
                </li>
                <li
                    v-if="shown.length === 0"
                    class="px-2 py-3 text-sm text-muted-foreground"
                >
                    Tidak ada pengguna aktif yang cocok.
                </li>
            </ul>
        </PopoverContent>
    </Popover>
</template>
