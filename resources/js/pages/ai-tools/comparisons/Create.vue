<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeftRight, History } from '@lucide/vue';
import DocumentComparisonController from '@/actions/App/Http/Controllers/AiTools/DocumentComparisonController';
import SourcePicker from '@/components/ai-tools/comparison/SourcePicker.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Bandingkan 2 Dokumen',
                href: DocumentComparisonController.create(),
            },
        ],
    },
});

defineProps<{ limits: { max_file_mb: number; max_pages: number } }>();
</script>

<template>
    <Head title="Bandingkan 2 Dokumen" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-wrap items-start justify-between gap-3 [&>header]:mb-0"
        >
            <Heading
                title="Bandingkan 2 Dokumen"
                description="Unggah dua dokumen (PDF teks atau scan), ambil dari arsip DCS, atau tempel teks. Hasilnya matriks per pasal dengan penandaan perubahan dan tinjauan strategis."
            />
            <Button variant="outline" as-child>
                <Link :href="DocumentComparisonController.index()">
                    <History /> Riwayat
                </Link>
            </Button>
        </div>

        <Form
            v-bind="DocumentComparisonController.store.form()"
            class="flex flex-col gap-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-6 lg:grid-cols-2">
                <SourcePicker
                    side="a"
                    :errors="errors"
                    :max-file-mb="limits.max_file_mb"
                    :max-pages="limits.max_pages"
                />
                <SourcePicker
                    side="b"
                    :errors="errors"
                    :max-file-mb="limits.max_file_mb"
                    :max-pages="limits.max_pages"
                />
            </div>

            <div class="flex">
                <Button
                    type="submit"
                    class="w-full md:w-auto"
                    :disabled="processing"
                >
                    <Spinner v-if="processing" />
                    <ArrowLeftRight v-else />
                    Bandingkan Dokumen
                </Button>
            </div>
        </Form>
    </div>
</template>
