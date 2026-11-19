<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue';
import PersonCombobox from '@/components/documents/config/types/PersonCombobox.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { SignatureColumn, SignerOption } from '@/types';

defineProps<{
    column: SignatureColumn;
    index: number;
    signerOptions: SignerOption[];
    first: boolean;
}>();
defineEmits<{ change: [patch: Partial<SignatureColumn>] }>();

const modes = [
    {
        value: 'person',
        label: 'Orang tertentu',
        hint: 'Administrator memilih satu orang di sini.',
    },
    {
        value: 'at_creation',
        label: 'Dipilih saat dokumen dibuat',
        hint: 'Penyusun memilih satu orang dengan peran di atas.',
    },
] as const;
</script>

<template>
    <div class="flex flex-col gap-3 border-t pt-3">
        <p
            v-if="column.fillMode === 'legacy'"
            class="flex items-start gap-1.5 text-xs text-warning-foreground"
            role="status"
        >
            <TriangleAlert class="mt-0.5 size-3.5 shrink-0" />
            Belum ditetapkan: kolom ini masih terbuka untuk semua pemegang
            peran. Pilih salah satu cara di bawah supaya hanya orang yang
            ditunjuk yang bisa menandatangani.
        </p>
        <fieldset class="flex flex-wrap gap-x-6 gap-y-2">
            <legend class="sr-only">
                Siapa yang menandatangani kolom {{ index + 1 }}
            </legend>
            <Label
                v-for="m in modes"
                :key="m.value"
                class="flex items-start gap-2 font-normal"
            >
                <input
                    type="radio"
                    :name="`mode-${first}-${index}-${column.label}`"
                    :value="m.value"
                    :checked="column.fillMode === m.value"
                    class="mt-1"
                    @change="
                        $emit('change', {
                            fillMode: m.value,
                            defaultUserId:
                                m.value === 'person'
                                    ? column.defaultUserId
                                    : null,
                        })
                    "
                />
                <span class="flex flex-col"
                    ><span>{{ m.label }}</span
                    ><span class="text-xs text-muted-foreground">{{
                        m.hint
                    }}</span></span
                >
            </Label>
        </fieldset>
        <div v-if="column.fillMode === 'person'" class="max-w-sm">
            <PersonCombobox
                :model-value="column.defaultUserId ?? null"
                :options="signerOptions"
                :label="`Penandatangan kolom ${index + 1}`"
                @update:model-value="$emit('change', { defaultUserId: $event })"
            />
        </div>
        <Label
            v-if="column.fillMode !== 'legacy' && !first"
            class="flex items-center gap-2 font-normal"
        >
            <Checkbox
                :model-value="column.allowCreator ?? false"
                @update:model-value="
                    $emit('change', { allowCreator: $event === true })
                "
            />
            Pembuat dokumen boleh menyetujui kolom ini
        </Label>
    </div>
</template>
