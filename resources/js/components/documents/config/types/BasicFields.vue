<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

export type BasicState = {
    code: string;
    name: string;
    description: string;
    layoutStyle: string;
    headerStyle: string;
    bodyStyle: string;
    letterhead: boolean;
    documentLevel: string;
    processOwner: string;
};

const model = defineModel<BasicState>({ required: true });

defineProps<{ errors: Record<string, string>; codeLocked: boolean }>();

function set<K extends keyof BasicState>(key: K, value: BasicState[K]): void {
    model.value = { ...model.value, [key]: value };
}

const layouts = [
    { value: 'standard', label: 'Standar (SOP, instruksi kerja)' },
    { value: 'memo', label: 'Memo' },
    { value: 'kebijakan', label: 'Kebijakan' },
];
const bodies = [
    { value: 'standard', label: 'Standar (judul biru)' },
    { value: 'formal', label: 'Formal SOP (angka romawi, rata kiri-kanan)' },
];
const headers = [
    { value: 'iso', label: 'Kop ISO (kotak metadata formal)' },
    { value: 'simple', label: 'Kop sederhana' },
];
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="flex flex-col gap-1.5">
            <Label for="type-code">Kode</Label>
            <Input
                id="type-code"
                :model-value="model.code"
                :disabled="codeLocked"
                maxlength="20"
                placeholder="Mis. SOP"
                class="uppercase"
                @update:model-value="set('code', String($event).toUpperCase())"
            />
            <p class="text-xs text-muted-foreground">
                {{
                    codeLocked
                        ? 'Kode tidak bisa diubah karena sudah menjadi bagian nomor dokumen.'
                        : 'Huruf besar dan angka saja; dipakai sebagai {DocType} pada nomor dokumen.'
                }}
            </p>
            <InputError :message="errors.code" />
        </div>
        <div class="flex flex-col gap-1.5">
            <Label for="type-name">Nama</Label>
            <Input
                id="type-name"
                :model-value="model.name"
                maxlength="150"
                placeholder="Mis. Standar Operasional Prosedur"
                @update:model-value="set('name', String($event))"
            />
            <InputError :message="errors.name" />
        </div>
        <div class="flex flex-col gap-1.5 sm:col-span-2">
            <Label for="type-description">Keterangan (opsional)</Label>
            <Textarea
                id="type-description"
                :model-value="model.description"
                rows="2"
                @update:model-value="set('description', String($event))"
            />
        </div>
        <div class="flex flex-col gap-1.5">
            <Label>Tata letak</Label>
            <Select
                :model-value="model.layoutStyle"
                @update:model-value="set('layoutStyle', String($event))"
            >
                <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="option in layouts"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>
        <div class="flex flex-col gap-1.5">
            <Label>Kop dokumen</Label>
            <Select
                :model-value="model.headerStyle"
                @update:model-value="set('headerStyle', String($event))"
            >
                <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="option in headers"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>
        <div class="flex flex-col gap-1.5">
            <Label>Gaya isi dokumen</Label>
            <Select
                :model-value="model.bodyStyle"
                @update:model-value="set('bodyStyle', String($event))"
            >
                <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="option in bodies"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>
        <div class="flex flex-col gap-1.5">
            <Label for="type-level">Tingkat dokumen</Label>
            <Input
                id="type-level"
                :model-value="model.documentLevel"
                placeholder="Internal"
                @update:model-value="set('documentLevel', String($event))"
            />
        </div>
        <div class="flex flex-col gap-1.5">
            <Label for="type-owner">Pemilik proses</Label>
            <Input
                id="type-owner"
                :model-value="model.processOwner"
                placeholder="Kosong = memakai departemen dokumen"
                @update:model-value="set('processOwner', String($event))"
            />
        </div>
        <Label class="flex items-center gap-2 font-normal sm:col-span-2">
            <Checkbox
                :model-value="model.letterhead"
                @update:model-value="set('letterhead', $event === true)"
            />
            Cetak kop surat perusahaan (nama, alamat, logo)
        </Label>
    </div>
</template>
