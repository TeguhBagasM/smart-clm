<script setup lang="ts">
import { ArrowDown, ArrowUp, Plus, Trash2 } from '@lucide/vue';
import Panel from '@/components/documents/Panel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { moveItem, removeItem } from '@/lib/arrays';
import type { TypeSection } from '@/types';

const model = defineModel<TypeSection[]>({ required: true });

defineProps<{ errors: Record<string, string> }>();

function edit(index: number, patch: Partial<TypeSection>): void {
    model.value = model.value.map((section, position) =>
        position === index ? { ...section, ...patch } : section,
    );
}
</script>

<template>
    <Panel title="Bagian isi bawaan" :count="model.length">
        <p class="mb-3 text-sm text-muted-foreground">
            Kerangka bagian yang sudah terisi saat penyusun membuat dokumen
            baru. Penyusun masih bisa mengubahnya.
        </p>
        <ol class="flex flex-col gap-3">
            <li
                v-for="(section, index) in model"
                :key="index"
                class="flex flex-col gap-2 rounded-lg border p-3"
            >
                <div class="flex items-center gap-2">
                    <span class="w-6 text-sm text-muted-foreground">
                        {{ index + 1 }}.
                    </span>
                    <Input
                        :model-value="section.heading"
                        placeholder="Judul bagian, mis. Tujuan"
                        :aria-label="`Judul bagian ${index + 1}`"
                        @update:model-value="
                            edit(index, { heading: String($event) })
                        "
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Naikkan"
                        :disabled="index === 0"
                        @click="model = moveItem(model, index, -1)"
                    >
                        <ArrowUp />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Turunkan"
                        :disabled="index === model.length - 1"
                        @click="model = moveItem(model, index, 1)"
                    >
                        <ArrowDown />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Hapus bagian"
                        @click="model = removeItem(model, index)"
                    >
                        <Trash2 class="text-destructive" />
                    </Button>
                </div>
                <InputError
                    :message="errors[`defaultSections.${index}.heading`]"
                />
                <Textarea
                    :model-value="section.content"
                    rows="2"
                    placeholder="Teks awal (opsional)"
                    :aria-label="`Isi awal bagian ${index + 1}`"
                    @update:model-value="
                        edit(index, { content: String($event) })
                    "
                />
            </li>
        </ol>
        <Button
            type="button"
            variant="outline"
            size="sm"
            class="mt-3"
            @click="model = [...model, { heading: '', content: '' }]"
        >
            <Plus /> Tambah bagian
        </Button>
    </Panel>
</template>
