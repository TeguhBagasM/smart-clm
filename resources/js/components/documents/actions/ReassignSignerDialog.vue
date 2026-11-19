<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { UserRoundCog } from '@lucide/vue';
import { ref } from 'vue';
import VersionSignerController from '@/actions/App/Http/Controllers/Documents/VersionSignerController';
import ActionDialog from '@/components/documents/actions/ActionDialog.vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { RecipientOption } from '@/types';

/** Replaces the person of one signature column on a running document (company administrators). */
defineProps<{
    documentId: string;
    versionId: string;
    order: number;
    label: string;
    options?: RecipientOption[];
}>();

const userId = ref('');
const reason = ref('');

function load(): void {
    router.reload({ only: ['recipient_options'] });
}
</script>

<template>
    <ActionDialog
        :form="
            VersionSignerController.reassign.form({
                document: documentId,
                version: versionId,
                order,
            })
        "
        :transform="() => ({ user_id: userId, reason })"
        trigger-label="Ganti penandatangan"
        trigger-variant="ghost"
        :trigger-icon="UserRoundCog"
        :title="`Ganti penandatangan: ${label}`"
        description="Tugas dan hak menandatangani pindah ke orang baru. Penggantian dicatat di riwayat dokumen beserta alasannya."
        submit-label="Ganti penandatangan"
        v-slot="{ errors }"
        @opened="load"
        @done="((userId = ''), (reason = ''))"
    >
        <div class="flex flex-col gap-1.5">
            <Label for="signer-user">Penandatangan baru</Label>
            <Select v-model="userId">
                <SelectTrigger id="signer-user" class="w-full"
                    ><SelectValue placeholder="Pilih orang…"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="person in options ?? []"
                        :key="person.id"
                        :value="String(person.id)"
                    >
                        {{ person.name }} · {{ person.role }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="errors.user_id" />
        </div>
        <div class="flex flex-col gap-1.5">
            <Label for="signer-reason">Alasan</Label>
            <Textarea
                id="signer-reason"
                v-model="reason"
                rows="3"
                maxlength="500"
                placeholder="Mis. penandatangan cuti panjang atau pindah tugas"
            />
            <InputError :message="errors.reason" />
        </div>
    </ActionDialog>
</template>
