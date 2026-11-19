<script setup lang="ts">
import { Check, Search } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import DocumentComparisonController from '@/actions/App/Http/Controllers/AiTools/DocumentComparisonController';
import { Input } from '@/components/ui/input';
import type { ArchiveOption } from '@/types';

const props = defineProps<{ name: string }>();
const selected = defineModel<ArchiveOption | null>({ required: true });

const term = ref('');
const options = ref<ArchiveOption[]>([]);
const loading = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;

async function load(): Promise<void> {
    loading.value = true;

    try {
        const answer = await fetch(
            DocumentComparisonController.archive({
                query: { q: term.value },
            }).url,
            {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            },
        );
        options.value = answer.ok ? await answer.json() : [];
    } finally {
        loading.value = false;
    }
}

function search(): void {
    clearTimeout(timer);
    timer = setTimeout(load, 300);
}

onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-2">
        <input
            v-if="selected"
            type="hidden"
            :name="props.name"
            :value="selected.id"
        />
        <div class="relative">
            <Search
                class="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground"
            />
            <Input
                v-model="term"
                type="search"
                class="pl-8"
                placeholder="Cari nomor atau judul dokumen DCS..."
                aria-label="Cari dokumen arsip"
                @input="search"
            />
        </div>
        <ul
            class="max-h-56 overflow-y-auto rounded-lg border"
            :aria-busy="loading"
        >
            <li
                v-if="!loading && options.length === 0"
                class="p-3 text-sm text-muted-foreground"
            >
                Tidak ada dokumen yang cocok.
            </li>
            <li v-for="option in options" :key="option.id">
                <button
                    type="button"
                    class="flex w-full items-start gap-2 border-b px-3 py-2 text-left text-sm last:border-b-0 hover:bg-accent focus-visible:bg-accent focus-visible:outline-none"
                    :aria-pressed="selected?.id === option.id"
                    @click="selected = option"
                >
                    <Check
                        :class="[
                            'mt-0.5 size-4 shrink-0 text-primary',
                            selected?.id === option.id ? '' : 'invisible',
                        ]"
                    />
                    <span class="flex flex-col">
                        <span class="font-medium">{{ option.label }}</span>
                        <span class="text-xs text-muted-foreground">
                            {{ option.version }} · {{ option.status }}
                        </span>
                    </span>
                </button>
            </li>
        </ul>
    </div>
</template>
