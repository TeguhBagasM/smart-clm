<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { ArrowLeft, Trash2, TriangleAlert } from '@lucide/vue';
import { onBeforeUnmount, watch } from 'vue';
import DocumentComparisonController from '@/actions/App/Http/Controllers/AiTools/DocumentComparisonController';
import ActionDialog from '@/components/documents/actions/ActionDialog.vue';
import ComparisonMatrix from '@/components/ai-tools/comparison/ComparisonMatrix.vue';
import ComparisonStatusBadge from '@/components/ai-tools/comparison/ComparisonStatusBadge.vue';
import Disclaimer from '@/components/ai-tools/comparison/Disclaimer.vue';
import ProgressPanel from '@/components/ai-tools/comparison/ProgressPanel.vue';
import StrategicReview from '@/components/ai-tools/comparison/StrategicReview.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import type { ComparisonDetail } from '@/types';

const props = defineProps<{ comparison: ComparisonDetail }>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Bandingkan 2 Dokumen',
                href: DocumentComparisonController.create(),
            },
            { title: 'Riwayat', href: DocumentComparisonController.index() },
        ],
    },
});

const { start, stop } = usePoll(
    2500,
    { only: ['comparison'] },
    { autoStart: false },
);

watch(
    () => props.comparison.status.value,
    (status) =>
        status === 'queued' || status === 'processing' ? start() : stop(),
    { immediate: true },
);
onBeforeUnmount(stop);
</script>

<template>
    <Head :title="`${comparison.label_a} vs ${comparison.label_b}`" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex min-w-0 flex-col gap-2">
                <Link
                    :href="DocumentComparisonController.index()"
                    class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="size-4" /> Riwayat
                </Link>
                <h1 class="text-xl font-semibold break-words">
                    {{ comparison.label_a }}
                    <span class="text-muted-foreground">dibanding</span>
                    {{ comparison.label_b }}
                </h1>
                <ComparisonStatusBadge
                    :status="comparison.status.value"
                    :label="comparison.status.label"
                />
            </div>
            <ActionDialog
                :form="
                    DocumentComparisonController.destroy.form({
                        comparison: comparison.id,
                    })
                "
                trigger-label="Hapus"
                :trigger-icon="Trash2"
                title="Hapus perbandingan?"
                description="Hasil dan berkas yang diunggah dihapus permanen dan tidak bisa dikembalikan."
                submit-variant="destructive"
            />
        </div>

        <Alert
            v-if="comparison.status.value === 'failed'"
            variant="destructive"
        >
            <TriangleAlert />
            <AlertTitle>Perbandingan gagal</AlertTitle>
            <AlertDescription>
                {{ comparison.error }}
                <Button as-child variant="outline" size="sm" class="mt-3">
                    <Link :href="DocumentComparisonController.create()">
                        Coba lagi dengan dokumen baru
                    </Link>
                </Button>
            </AlertDescription>
        </Alert>

        <ProgressPanel
            v-else-if="comparison.status.value !== 'completed'"
            :comparison="comparison"
        />

        <template v-else>
            <Alert v-if="comparison.warnings.length">
                <TriangleAlert />
                <AlertTitle>Perhatian</AlertTitle>
                <AlertDescription>
                    <ul class="list-disc pl-4">
                        <li
                            v-for="warning in comparison.warnings"
                            :key="warning"
                        >
                            {{ warning }}
                        </li>
                    </ul>
                </AlertDescription>
            </Alert>
            <Disclaimer />
            <ComparisonMatrix :comparison="comparison" />
            <StrategicReview
                v-if="comparison.summary"
                :summary="comparison.summary"
            />
        </template>
    </div>
</template>
