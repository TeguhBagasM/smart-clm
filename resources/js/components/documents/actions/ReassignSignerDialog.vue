<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { UserRoundCog } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import VersionSignerController from '@/actions/App/Http/Controllers/Documents/VersionSignerController';
import ActionDialog from '@/components/documents/actions/ActionDialog.vue';
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';
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
const props = defineProps<{
    documentId: string;
    versionId: string;
    order: number;
    label: string;
    options?: RecipientOption[];
}>();

const userId = ref<string | null>(null);
const reason = ref('');

/**
 * The list arrives only when the dialog opens (a partial reload). A refused form
 * comes back without it, so what was loaded is kept for the rest of the visit.
 */
const loaded = ref<RecipientOption[]>([]);
watch(
    () => props.options,
    (next) => {
        if (next?.length) loaded.value = next;
    },
    { immediate: true },
);
const people = computed(() =>
    loaded.value.map((p) => ({
        value: String(p.id),
        label: p.name,
        description: p.role,
    })),
);

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
        :transform="() => ({ user_id: userId ?? '', reason })"
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
            <SearchSelect
                id="signer-user"
                v-model="userId"
                :options="people"
                label="Penandatangan baru"
                placeholder="Pilih orang…"
                search-placeholder="Cari nama atau jabatan…"
            />
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
