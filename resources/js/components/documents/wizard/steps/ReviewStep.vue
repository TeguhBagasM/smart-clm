<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, Circle, FileCog, Upload } from '@lucide/vue';
import { computed, ref } from 'vue';
import DocumentController from '@/actions/App/Http/Controllers/Documents/DocumentController';
import DocumentDraftController from '@/actions/App/Http/Controllers/Documents/DocumentDraftController';
import VersionWorkflowController from '@/actions/App/Http/Controllers/Documents/VersionWorkflowController';
import ActionDialog from '@/components/documents/actions/ActionDialog.vue';
import { steps } from '@/components/documents/wizard/steps';
import { Button } from '@/components/ui/button';
import { useWizard } from '@/composables/useDraftWizard';
import type { Draft } from '@/types';

const props = defineProps<{ draft: Draft }>();
const { flush, documentId, versionId, status } = useWizard();
const busy = ref(false);
const params = { document: documentId, version: versionId };

const items = computed(() =>
    steps
        .filter((step) => step.done)
        .map((step) => ({
            label: step.label,
            ok: props.draft.completeness[step.done!],
        })),
);
const ready = computed(() => props.draft.completeness.basic);

async function compose(): Promise<void> {
    busy.value = true;
    await flush();

    if (status.value === 'conflict' || status.value === 'error') {
        busy.value = false;

        return;
    }

    router.post(
        DocumentDraftController.render.url(params),
        {},
        {
            preserveScroll: true,
            onFinish: () => (busy.value = false),
        },
    );
}
</script>

<template>
    <div class="flex flex-col gap-5">
        <ul class="grid gap-2 sm:grid-cols-2">
            <li
                v-for="item in items"
                :key="item.label"
                class="flex items-center gap-2 rounded-lg border p-3 text-sm"
            >
                <Check v-if="item.ok" class="size-4 text-success" />
                <Circle v-else class="size-4 text-muted-foreground" />
                <span>{{ item.label }}</span>
                <span class="ml-auto text-xs text-muted-foreground">{{
                    item.ok ? 'Terisi' : 'Belum'
                }}</span>
            </li>
        </ul>

        <div class="flex flex-col gap-2 rounded-lg border p-4">
            <h3 class="text-sm font-medium">1. Susun PDF</h3>
            <p class="text-sm text-muted-foreground">
                {{
                    draft.has_file
                        ? 'PDF sudah ada. Susun ulang bila isi berubah.'
                        : 'Belum ada PDF. Susun dari isi yang sudah diisi.'
                }}
            </p>
            <Button
                class="w-full self-start sm:w-auto"
                :disabled="busy || !ready"
                @click="compose"
            >
                <FileCog />
                {{
                    busy
                        ? 'Menyusun…'
                        : draft.has_file
                          ? 'Susun ulang PDF'
                          : 'Susun PDF'
                }}
            </Button>
        </div>

        <div class="flex flex-col gap-2 rounded-lg border p-4">
            <h3 class="text-sm font-medium">2. Ajukan tanda tangan</h3>
            <p class="text-sm text-muted-foreground">
                Setelah diajukan, dokumen masuk antrean tanda tangan.
            </p>
            <div class="flex flex-wrap gap-2">
                <ActionDialog
                    :form="VersionWorkflowController.submit.form(params)"
                    trigger-label="Ajukan tanda tangan"
                    :trigger-icon="Upload"
                    trigger-variant="default"
                    title="Ajukan untuk tanda tangan?"
                    description="Dokumen masuk antrean tanda tangan dan tidak bisa disusun ulang selama diproses."
                />
                <Button variant="outline" as-child>
                    <a
                        :href="
                            DocumentController.show.url({
                                document: documentId,
                            })
                        "
                        >Lihat halaman dokumen</a
                    >
                </Button>
            </div>
        </div>
    </div>
</template>
