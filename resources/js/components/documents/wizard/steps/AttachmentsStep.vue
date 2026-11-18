<script setup lang="ts">
import { Plus } from '@lucide/vue';
import ClausePicker from '@/components/documents/config/types/ClausePicker.vue';
import AppendixRow from '@/components/documents/wizard/steps/AppendixRow.vue';
import { Button } from '@/components/ui/button';
import { useWizard } from '@/composables/useDraftWizard';
import { removeItem } from '@/lib/arrays';
import type { DraftAppendix } from '@/types';

const { state, touch, options } = useWizard();

function setAppendix(next: DraftAppendix[]): void {
    state.appendix = next;
    touch('appendix');
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <section class="flex flex-col gap-3">
            <h3 class="text-sm font-medium">Lampiran</h3>
            <p
                v-if="state.appendix.length === 0"
                class="text-sm text-muted-foreground"
            >
                Belum ada lampiran. Lewati langkah ini bila dokumen tidak punya
                lampiran.
            </p>
            <ul class="flex flex-col gap-3">
                <AppendixRow
                    v-for="(item, index) in state.appendix"
                    :key="index"
                    :model-value="item"
                    :index="index"
                    @update:model-value="
                        setAppendix(
                            state.appendix.map((row, p) =>
                                p === index ? $event : row,
                            ),
                        )
                    "
                    @remove="setAppendix(removeItem(state.appendix, index))"
                />
            </ul>
            <Button
                type="button"
                variant="outline"
                class="self-start"
                @click="
                    setAppendix([...state.appendix, { label: '', url: '' }])
                "
            >
                <Plus /> Tambah lampiran
            </Button>
        </section>
        <ClausePicker
            :model-value="state.clause_ids"
            :options="options.clauses"
            title="Klausul"
            description="Klausul yang dicantumkan pada dokumen ini."
            @update:model-value="
                state.clause_ids = $event;
                touch('clauses');
            "
        />
    </div>
</template>
