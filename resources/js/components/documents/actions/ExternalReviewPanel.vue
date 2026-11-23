<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Copy, LinkIcon, LockOpen, Unlink } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import VersionExternalReviewController from '@/actions/App/Http/Controllers/Documents/VersionExternalReviewController';
import ActionDialog from '@/components/documents/actions/ActionDialog.vue';
import Panel from '@/components/documents/Panel.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/format';
import type { VersionDetail } from '@/types';

const props = defineProps<{ documentId: string; version: VersionDetail }>();

const link = ref<string | null>(null);
let stop: (() => void) | null = null;

// The address is flashed once, in the answer to "Buat tautan"; it is not stored anywhere readable.
onMounted(() => {
    stop = router.on('flash', (event) => {
        const url = (event as CustomEvent).detail?.flash?.external_review_url;
        if (typeof url === 'string') link.value = url;
    });
});
onBeforeUnmount(() => stop?.());

const params = computed(() => ({
    document: props.documentId,
    version: props.version.id,
}));

async function copy(): Promise<void> {
    try {
        await navigator.clipboard.writeText(link.value ?? '');
        toast.success('Tautan disalin.');
    } catch {
        toast.error(
            'Tidak bisa menyalin otomatis. Pilih teks tautan lalu salin.',
        );
    }
}
</script>

<template>
    <Panel title="Tinjauan pihak luar">
        <div class="flex flex-col gap-3 text-sm">
            <p v-if="!version.external_review" class="text-muted-foreground">
                Belum ada tautan. Buat tautan agar auditor atau mitra bisa
                membaca dokumen ini dan memberi komentar tanpa akun.
            </p>
            <div v-else class="flex flex-wrap items-center gap-2">
                <Badge variant="outline">
                    {{
                        version.external_review.locked
                            ? 'Terkunci (sudah disetujui)'
                            : 'Tautan aktif'
                    }}
                </Badge>
                <span class="text-muted-foreground">
                    {{
                        version.external_review.expires_at
                            ? `Berlaku sampai ${formatDate(version.external_review.expires_at)}`
                            : 'Tanpa batas waktu'
                    }}
                </span>
            </div>

            <div
                v-if="link"
                class="flex flex-col gap-1.5 rounded-lg border p-3"
            >
                <Label for="external-link">
                    Tautan baru (hanya tampil sekarang)
                </Label>
                <div class="flex gap-2">
                    <Input
                        id="external-link"
                        :model-value="link"
                        readonly
                        @focus="($event.target as HTMLInputElement).select()"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        aria-label="Salin tautan"
                        @click="copy"
                    >
                        <Copy />
                    </Button>
                </div>
                <p class="text-xs text-muted-foreground">
                    Simpan atau kirim sekarang. Tautan lama berhenti berfungsi.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <ActionDialog
                    v-if="version.can.start_external_review"
                    :form="VersionExternalReviewController.enable.form(params)"
                    :trigger-label="
                        version.external_review
                            ? 'Buat tautan baru'
                            : 'Buat tautan'
                    "
                    :trigger-icon="LinkIcon"
                    :trigger-variant="
                        version.external_review ? 'outline' : 'default'
                    "
                    title="Buat tautan review"
                    description="Siapa pun yang memegang tautan ini bisa membaca dokumen dan berkomentar tanpa akun. Tautan yang sedang aktif langsung berhenti berfungsi."
                    submit-label="Buat tautan"
                    v-slot="{ errors }"
                >
                    <div class="flex flex-col gap-1.5">
                        <Label for="expires-in-days">Berlaku berapa hari</Label>
                        <Input
                            id="expires-in-days"
                            name="expires_in_days"
                            type="number"
                            min="1"
                            max="90"
                            inputmode="numeric"
                            default-value="7"
                        />
                        <p class="text-xs text-muted-foreground">
                            Kosongkan agar tidak kedaluwarsa (tidak disarankan).
                        </p>
                        <InputError :message="errors.expires_in_days" />
                    </div>
                </ActionDialog>
                <ActionDialog
                    v-if="version.external_review?.locked"
                    :form="VersionExternalReviewController.unlock.form(params)"
                    trigger-label="Buka kunci"
                    :trigger-icon="LockOpen"
                    title="Buka kunci tinjauan?"
                    description="Pihak luar bisa menulis komentar lagi setelah sebelumnya menyetujui."
                />
                <ActionDialog
                    v-if="
                        version.external_review &&
                        version.can.manage_external_review
                    "
                    :form="VersionExternalReviewController.disable.form(params)"
                    trigger-label="Nonaktifkan"
                    :trigger-icon="Unlink"
                    submit-variant="destructive"
                    title="Nonaktifkan tautan?"
                    description="Tautan berhenti berfungsi. Komentar dan persetujuan yang sudah masuk tetap tersimpan."
                />
            </div>
        </div>
    </Panel>
</template>
