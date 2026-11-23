<script setup lang="ts">
import {
    Ban,
    Languages,
    PencilLine,
    CalendarX,
    CheckCheck,
    PenLine,
    Undo2,
    Upload,
} from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import DocumentDraftController from '@/actions/App/Http/Controllers/Documents/DocumentDraftController';
import { Button } from '@/components/ui/button';
import VersionDistributionController from '@/actions/App/Http/Controllers/Documents/VersionDistributionController';
import VersionTranslationController from '@/actions/App/Http/Controllers/Documents/VersionTranslationController';
import VersionReleaseController from '@/actions/App/Http/Controllers/Documents/VersionReleaseController';
import VersionWorkflowController from '@/actions/App/Http/Controllers/Documents/VersionWorkflowController';
import ActionDialog from '@/components/documents/actions/ActionDialog.vue';
import DistributeDialog from '@/components/documents/actions/DistributeDialog.vue';
import ReleaseDialog from '@/components/documents/actions/ReleaseDialog.vue';
import ReviewScheduleDialog from '@/components/documents/actions/ReviewScheduleDialog.vue';
import RequestChangesDialog from '@/components/documents/actions/RequestChangesDialog.vue';
import type { RecipientOption, VersionDetail } from '@/types';

const props = defineProps<{
    documentId: string;
    version: VersionDetail;
    recipientOptions?: RecipientOption[];
}>();

const params = computed(() => ({
    document: props.documentId,
    version: props.version.id,
}));

/** Actions that only ask for a confirmation, in the order they are offered. */
const confirmations = computed(() => [
    {
        show: props.version.can.submit,
        form: VersionWorkflowController.submit.form(params.value),
        label: 'Ajukan tanda tangan',
        icon: Upload,
        variant: 'default' as const,
        title: 'Ajukan untuk tanda tangan?',
        description:
            'Dokumen masuk antrean tanda tangan dan tidak bisa disusun ulang selama diproses.',
    },
    {
        show: props.version.can.sign,
        form: VersionWorkflowController.sign.form(params.value),
        label: 'Tanda tangani',
        icon: PenLine,
        variant: 'default' as const,
        title: 'Tanda tangani dokumen ini?',
        description:
            'Nama, jabatan, dan tanggal Anda dibubuhkan pada kolom yang sedang giliran. Tindakan ini tercatat.',
    },
    {
        show: props.version.can.return_to_draft,
        form: VersionWorkflowController.returnToDraft.form(params.value),
        label: 'Tarik dari antrean',
        icon: Undo2,
        variant: 'outline' as const,
        title: 'Tarik dokumen dari antrean tanda tangan?',
        description:
            'Semua tanda tangan yang sudah masuk dibatalkan dan dokumen kembali menjadi draft.',
    },
    {
        show: props.version.can.cancel_release,
        form: VersionReleaseController.cancel.form(params.value),
        label: 'Batalkan jadwal rilis',
        icon: CalendarX,
        variant: 'outline' as const,
        title: 'Batalkan jadwal rilis?',
        description:
            'Versi tetap disetujui dan menunggu untuk diberlakukan secara manual.',
    },
    {
        show: props.version.can.withdraw,
        form: VersionReleaseController.withdraw.form(params.value),
        label: 'Tarik dokumen',
        icon: Ban,
        variant: 'destructive' as const,
        title: 'Tarik dokumen ini?',
        description:
            'Dokumen menjadi tidak berlaku tanpa pengganti dan watermark-nya berubah menjadi UNCONTROLLED.',
    },
    {
        show: props.version.can.translate,
        form: VersionTranslationController.store.form(params.value),
        label: props.version.has_translation
            ? 'Perbarui terjemahan Inggris'
            : 'Terjemahkan ke Inggris',
        icon: Languages,
        variant: 'outline' as const,
        title: 'Buat terjemahan Inggris?',
        description:
            'Isi dokumen dikirim ke layanan AI untuk diterjemahkan, lalu hasilnya menjadi salinan referensi (tidak ditandatangani). Proses bisa sampai 2 menit; jangan tutup halaman ini.',
    },
    {
        show: props.version.can.acknowledge,
        form: VersionDistributionController.acknowledge.form(params.value),
        label: 'Saya sudah membaca',
        icon: CheckCheck,
        variant: 'outline' as const,
        title: 'Konfirmasi sudah membaca dan memahami?',
        description: 'Konfirmasi Anda tercatat sebagai tanda terima baca.',
    },
]);

const any = computed(
    () =>
        confirmations.value.some((item) => item.show) ||
        Object.values(props.version.can).some(Boolean),
);
</script>

<template>
    <div
        v-if="any"
        class="flex flex-wrap items-center gap-2"
        role="toolbar"
        aria-label="Tindakan untuk versi ini"
    >
        <Button v-if="version.can.edit" as-child>
            <Link :href="DocumentDraftController.edit(params)">
                <PencilLine /> Lanjutkan menyusun
            </Link>
        </Button>
        <template v-for="item in confirmations" :key="item.label">
            <ActionDialog
                v-if="item.show"
                :form="item.form"
                :trigger-label="item.label"
                :trigger-icon="item.icon"
                :trigger-variant="item.variant"
                :submit-variant="
                    item.variant === 'destructive' ? 'destructive' : 'default'
                "
                :title="item.title"
                :description="item.description"
            />
        </template>
        <RequestChangesDialog
            v-if="version.can.request_changes"
            :document-id="documentId"
            :version-id="version.id"
        />
        <ReleaseDialog
            v-if="version.can.release"
            :document-id="documentId"
            :version-id="version.id"
        />
        <ReviewScheduleDialog
            v-if="version.can.schedule_review"
            :key="`${version.id}-${version.review_due_at}`"
            :document-id="documentId"
            :version-id="version.id"
            :due-at="version.review_due_at"
            :reminder-days="version.review_reminder_days"
        />
        <DistributeDialog
            v-if="version.can.distribute"
            :document-id="documentId"
            :version-id="version.id"
            :options="recipientOptions"
        />
    </div>
</template>
