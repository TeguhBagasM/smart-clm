<script setup lang="ts">
import { ref } from 'vue';
import ChoiceButtons from '@/components/ChoiceButtons.vue';
import ArchivePicker from '@/components/ai-tools/comparison/ArchivePicker.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { ArchiveOption } from '@/types';

const props = defineProps<{
    side: 'a' | 'b';
    errors: Record<string, string>;
    maxFileMb: number;
    maxPages: number;
}>();

const kind = ref('upload');
const archived = ref<ArchiveOption | null>(null);
const upper = props.side.toUpperCase();
</script>

<template>
    <section
        class="flex flex-col gap-4 rounded-xl border p-4 md:p-5"
        :aria-labelledby="`source-${side}`"
    >
        <h2 :id="`source-${side}`" class="text-lg font-semibold">
            Dokumen {{ upper }}
        </h2>

        <ChoiceButtons
            v-model="kind"
            :label="`Sumber dokumen ${upper}`"
            :options="[
                ['upload', 'Unggah PDF'],
                ['archive', 'Dari arsip'],
                ['paste', 'Tempel teks'],
            ]"
        />
        <input type="hidden" :name="`${side}[kind]`" :value="kind" />
        <InputError :message="errors[`${side}.kind`]" />

        <div class="flex flex-col gap-1.5">
            <Label :for="`label-${side}`">Nama dokumen (opsional)</Label>
            <Input
                :id="`label-${side}`"
                :name="`${side}[label]`"
                maxlength="300"
                :placeholder="`Dokumen ${upper}`"
            />
            <InputError :message="errors[`${side}.label`]" />
        </div>

        <div v-if="kind === 'upload'" class="flex flex-col gap-1.5">
            <Label :for="`file-${side}`">Berkas PDF</Label>
            <input
                :id="`file-${side}`"
                type="file"
                :name="`${side}[file]`"
                accept="application/pdf"
                class="text-sm file:mr-3 file:rounded-md file:border file:bg-background file:px-3 file:py-1.5 file:text-sm file:font-medium"
            />
            <p class="text-xs text-muted-foreground">
                PDF teks maupun hasil scan, maksimal {{ maxFileMb }} MB dan
                {{ maxPages }} halaman. Halaman scan dibaca dengan OCR.
            </p>
            <InputError :message="errors[`${side}.file`]" />
        </div>

        <div v-else-if="kind === 'archive'" class="flex flex-col gap-1.5">
            <Label>Dokumen DCS perusahaan Anda</Label>
            <ArchivePicker v-model="archived" :name="`${side}[version]`" />
            <InputError :message="errors[`${side}.version`]" />
        </div>

        <div v-else class="flex flex-col gap-1.5">
            <Label :for="`text-${side}`">Isi dokumen</Label>
            <Textarea
                :id="`text-${side}`"
                :name="`${side}[text]`"
                rows="10"
                :placeholder="`Tempel isi Dokumen ${upper} di sini...`"
            />
            <InputError :message="errors[`${side}.text`]" />
        </div>
    </section>
</template>
