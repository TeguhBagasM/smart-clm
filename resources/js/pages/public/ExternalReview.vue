<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { LinkIcon, Lock } from '@lucide/vue';
import { ref } from 'vue';
import ExternalApprove from '@/components/documents/external/ExternalApprove.vue';
import ExternalCommentForm from '@/components/documents/external/ExternalCommentForm.vue';
import ExternalCommentList from '@/components/documents/external/ExternalCommentList.vue';
import ExternalDocumentView from '@/components/documents/external/ExternalDocumentView.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { useReviewerName } from '@/composables/useReviewerName';
import { formatDate } from '@/lib/format';
import type { ExternalAnchor, ExternalReviewData } from '@/types';

defineProps<{ token: string | null; review: ExternalReviewData | null }>();

const name = useReviewerName();
const anchor = ref<ExternalAnchor>({ field: 'general', sectionIndex: null });

function comment(next: ExternalAnchor): void {
    anchor.value = next;
    document.getElementById('komentar')?.scrollIntoView({ behavior: 'smooth' });
}
</script>

<template>
    <Head title="Tinjauan dokumen">
        <meta name="robots" content="noindex" />
    </Head>

    <Alert v-if="review === null || token === null">
        <LinkIcon />
        <AlertTitle>Tautan tidak berlaku</AlertTitle>
        <AlertDescription>
            Tautan ini salah, sudah kedaluwarsa, atau sudah ditutup. Minta
            pemilik dokumen mengirim tautan yang baru.
        </AlertDescription>
    </Alert>

    <div v-else class="flex flex-col gap-6">
        <header class="flex flex-col gap-1">
            <span class="font-mono text-sm text-muted-foreground">
                {{ review.document.number }} · {{ review.document.type }}
            </span>
            <h1 class="text-2xl font-semibold tracking-tight">
                {{ review.document.title }}
            </h1>
            <p class="text-sm text-muted-foreground">
                Terbitan {{ review.document.major }} Revisi
                {{ review.document.minor }}
                <template v-if="review.expires_at">
                    · tautan berlaku sampai {{ formatDate(review.expires_at) }}
                </template>
            </p>
        </header>

        <Alert v-if="review.locked">
            <Lock />
            <AlertTitle>Tinjauan sudah Anda setujui</AlertTitle>
            <AlertDescription>
                Komentar baru dikunci. Hubungi pemilik dokumen bila perlu
                membukanya kembali.
            </AlertDescription>
        </Alert>

        <ExternalDocumentView
            :document="review.document"
            :can-comment="!review.locked"
            @comment="comment"
        />

        <section class="flex flex-col gap-3">
            <h2 class="text-lg font-semibold">
                Komentar ({{ review.comments.length }})
            </h2>
            <ExternalCommentList :comments="review.comments" />
        </section>

        <template v-if="!review.locked">
            <ExternalCommentForm
                v-model:name="name"
                v-model:anchor="anchor"
                :token="token"
                :sections="review.document.sections"
            />
            <div
                class="flex flex-col gap-2 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm">
                    Sudah selesai membaca dan tidak ada yang perlu diubah?
                </p>
                <ExternalApprove :token="token" :name="name" />
            </div>
        </template>
    </div>
</template>
