<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DocumentController from '@/actions/App/Http/Controllers/Documents/DocumentController';
import DocumentHeader from '@/components/documents/DocumentHeader.vue';
import ReceiptSummary from '@/components/documents/ReceiptSummary.vue';
import ReviewCommentList from '@/components/documents/ReviewCommentList.vue';
import SignatureRoster from '@/components/documents/SignatureRoster.vue';
import TimelinePreview from '@/components/documents/TimelinePreview.vue';
import VersionFacts from '@/components/documents/VersionFacts.vue';
import VersionList from '@/components/documents/VersionList.vue';
import VersionPreview from '@/components/documents/VersionPreview.vue';
import type { DocumentDetail, EventRow, VersionDetail } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dokumen Terkontrol', href: DocumentController.index() },
        ],
    },
});

defineProps<{
    document: DocumentDetail;
    version: VersionDetail | null;
    recent_events: EventRow[];
}>();
</script>

<template>
    <Head :title="document.title" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <DocumentHeader :document="document" show-history-link />

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div v-if="version" class="flex min-w-0 flex-col gap-6">
                <VersionPreview :document-id="document.id" :version="version" />
                <ReviewCommentList :comments="version.comments" />
            </div>
            <p v-else class="text-muted-foreground">
                Dokumen ini belum memiliki versi.
            </p>

            <aside class="flex flex-col gap-4">
                <VersionList
                    :document-id="document.id"
                    :versions="document.versions"
                    :selected-id="version?.id ?? null"
                />
                <template v-if="version">
                    <VersionFacts :version="version" />
                    <SignatureRoster :roster="version.roster" />
                    <ReceiptSummary :receipts="version.receipts" />
                </template>
                <TimelinePreview
                    :document-id="document.id"
                    :events="recent_events"
                />
            </aside>
        </div>
    </div>
</template>
