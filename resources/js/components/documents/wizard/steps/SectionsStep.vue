<script setup lang="ts">
import { Plus } from '@lucide/vue';
import LanguagePicker from '@/components/documents/wizard/LanguagePicker.vue';
import SectionCard from '@/components/documents/wizard/steps/SectionCard.vue';
import { Button } from '@/components/ui/button';
import { useWizard } from '@/composables/useDraftWizard';
import { moveItem, removeItem } from '@/lib/arrays';
import type { DraftSection } from '@/types';

const { state, touch } = useWizard();

function change(next: DraftSection[]): void {
    state.sections = next;
    touch('sections');
}

function edit(index: number, section: DraftSection): void {
    change(
        state.sections.map((item, position) =>
            position === index ? section : item,
        ),
    );
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <LanguagePicker />
        <p
            v-if="state.sections.length === 0"
            class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
        >
            Belum ada bagian. Tambahkan bagian pertama, mis. Tujuan, Ruang
            Lingkup, atau Prosedur.
        </p>
        <ol class="flex flex-col gap-3">
            <SectionCard
                v-for="(section, index) in state.sections"
                :key="index"
                :model-value="section"
                :index="index"
                :first="index === 0"
                :last="index === state.sections.length - 1"
                @update:model-value="edit(index, $event)"
                @move="change(moveItem(state.sections, index, $event))"
                @remove="change(removeItem(state.sections, index))"
            />
        </ol>
        <Button
            type="button"
            variant="outline"
            class="self-start"
            @click="change([...state.sections, { heading: '', content: '' }])"
        >
            <Plus /> Tambah bagian
        </Button>
    </div>
</template>
