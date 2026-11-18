<script setup lang="ts">
import { Rocket } from '@lucide/vue';
import { ref } from 'vue';
import VersionReleaseController from '@/actions/App/Http/Controllers/Documents/VersionReleaseController';
import ActionDialog from '@/components/documents/actions/ActionDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{ documentId: string; versionId: string }>();

const scheduled = ref(false);
const tomorrow = new Date(Date.now() + 86_400_000).toISOString().slice(0, 10);
</script>

<template>
    <ActionDialog
        :form="
            VersionReleaseController.store.form({
                document: documentId,
                version: versionId,
            })
        "
        :transform="
            (data) => (scheduled ? data : { ...data, effective_at: '' })
        "
        trigger-label="Berlakukan"
        :trigger-icon="Rocket"
        trigger-variant="default"
        title="Berlakukan versi ini"
        description="Versi yang sedang berlaku otomatis menjadi tidak berlaku pada saat yang sama."
        :submit-label="scheduled ? 'Jadwalkan' : 'Berlakukan sekarang'"
        v-slot="{ errors }"
    >
        <div class="grid grid-cols-2 gap-2">
            <Button
                type="button"
                :variant="scheduled ? 'outline' : 'default'"
                @click="scheduled = false"
            >
                Sekarang
            </Button>
            <Button
                type="button"
                :variant="scheduled ? 'default' : 'outline'"
                @click="scheduled = true"
            >
                Jadwalkan
            </Button>
        </div>
        <div v-if="scheduled" class="flex flex-col gap-1.5">
            <Label for="effective-at">Berlaku mulai</Label>
            <Input
                id="effective-at"
                name="effective_at"
                type="date"
                :min="tomorrow"
                required
            />
            <p class="text-xs text-muted-foreground">
                Versi tetap berstatus disetujui sampai tanggal ini tiba, lalu
                diberlakukan otomatis.
            </p>
            <InputError :message="errors.effective_at" />
        </div>
    </ActionDialog>
</template>
