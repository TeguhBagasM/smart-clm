<script setup lang="ts">
import { CalendarClock } from '@lucide/vue';
import VersionReviewScheduleController from '@/actions/App/Http/Controllers/Documents/VersionReviewScheduleController';
import ActionDialog from '@/components/documents/actions/ActionDialog.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    documentId: string;
    versionId: string;
    dueAt: string | null;
    reminderDays: number;
}>();

const today = new Date().toISOString().slice(0, 10);
const due = props.dueAt === null ? '' : props.dueAt.slice(0, 10);
</script>

<template>
    <ActionDialog
        :form="
            VersionReviewScheduleController.update.form({
                document: documentId,
                version: versionId,
            })
        "
        trigger-label="Jadwal tinjau ulang"
        :trigger-icon="CalendarClock"
        title="Jadwal tinjau ulang"
        description="Tentukan kapan dokumen ini perlu ditinjau lagi. Pemilik dokumen dan bagian pengendali dokumen akan diingatkan."
        submit-label="Simpan jadwal"
        v-slot="{ errors }"
    >
        <div class="flex flex-col gap-1.5">
            <Label for="review-due-at">Tinjau ulang pada</Label>
            <Input
                id="review-due-at"
                name="review_due_at"
                type="date"
                :min="today"
                :default-value="due"
            />
            <p class="text-xs text-muted-foreground">
                Kosongkan tanggal untuk menghapus jadwal.
            </p>
            <InputError :message="errors.review_due_at" />
        </div>
        <div class="flex flex-col gap-1.5">
            <Label for="reminder-days">Ingatkan berapa hari sebelumnya</Label>
            <Input
                id="reminder-days"
                name="reminder_days"
                type="number"
                min="1"
                max="365"
                inputmode="numeric"
                :default-value="reminderDays"
            />
            <InputError :message="errors.reminder_days" />
        </div>
    </ActionDialog>
</template>
