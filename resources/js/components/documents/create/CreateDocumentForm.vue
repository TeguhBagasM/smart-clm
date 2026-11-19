<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Form, Link } from '@inertiajs/vue3';
import { reactive } from 'vue';
import ChoiceButtons from '@/components/ChoiceButtons.vue';
import DocumentController from '@/actions/App/Http/Controllers/Documents/DocumentController';
import DocumentCreateController from '@/actions/App/Http/Controllers/Documents/DocumentCreateController';
import DepartmentField from '@/components/documents/create/DepartmentField.vue';
import NumberPreview from '@/components/documents/create/NumberPreview.vue';
import UploadFields from '@/components/documents/create/UploadFields.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import type { ChoiceOption } from '@/types';

const props = defineProps<{
    types: { code: string; name: string; description: string | null }[];
    departments: ChoiceOption[];
    owners: ChoiceOption[];
    me: string;
}>();

const state = reactive({
    mode: 'compose',
    docTypeCode: props.types[0]?.code ?? '',
    department: '',
    title: '',
    ownerUserId: props.me,
    languageMode: 'single',
});

function transform(
    data: Record<string, FormDataConvertible>,
): Record<string, FormDataConvertible> {
    return { ...data, ...state };
}
</script>

<template>
    <Form
        v-bind="DocumentCreateController.store.form()"
        :transform="transform"
        class="flex flex-col gap-5"
        v-slot="{ errors, processing }"
    >
        <fieldset class="flex flex-col gap-2">
            <legend class="mb-1 text-sm font-medium">Cara membuat</legend>
            <ChoiceButtons
                v-model="state.mode"
                label="Cara membuat"
                :options="[
                    ['compose', 'Susun di sistem'],
                    ['upload', 'Unggah dokumen jadi'],
                ]"
            />
        </fieldset>

        <div class="flex flex-col gap-1.5">
            <Label>Jenis dokumen</Label>
            <SearchSelect
                v-model="state.docTypeCode"
                :options="
                    types.map((t) => ({
                        value: t.code,
                        label: t.code,
                        description: t.name,
                    }))
                "
                label="Jenis dokumen"
                search-placeholder="Cari jenis dokumen…"
            />
            <InputError :message="errors.docTypeCode" />
            <p class="text-xs text-muted-foreground">
                Nomor terbitan dan revisi diatur otomatis oleh sistem.
            </p>
        </div>

        <DepartmentField
            v-model="state.department"
            :options="departments"
            :error="errors.department"
        />

        <div class="flex flex-col gap-1.5">
            <Label>Nomor dokumen</Label>
            <NumberPreview
                :type-code="state.docTypeCode"
                :department="state.department"
            />
        </div>

        <div class="flex flex-col gap-1.5">
            <Label for="title">Judul dokumen</Label>
            <Input
                id="title"
                v-model="state.title"
                maxlength="500"
                placeholder="Mis. Prosedur Pengadaan Barang"
            />
            <InputError :message="errors.title" />
        </div>

        <UploadFields
            v-if="state.mode === 'upload'"
            :errors="errors"
            @suggested="
                (data) => {
                    state.title = data.title ?? state.title;
                    state.docTypeCode = data.docTypeCode ?? state.docTypeCode;
                    state.department = data.department ?? state.department;
                }
            "
        />
        <template v-else>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1.5">
                    <Label>Pemilik dokumen</Label>
                    <SearchSelect
                        v-model="state.ownerUserId"
                        :options="
                            owners.map((o) => ({
                                value: o.value,
                                label: o.label,
                            }))
                        "
                        label="Pemilik dokumen"
                        search-placeholder="Cari nama…"
                    />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label>Bahasa dokumen</Label>
                    <ChoiceButtons
                        v-model="state.languageMode"
                        label="Bahasa dokumen"
                        :options="[
                            ['single', 'Indonesia'],
                            ['multi', 'Indonesia + Inggris'],
                        ]"
                    />
                </div>
            </div>
        </template>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Button variant="outline" as-child>
                <Link :href="DocumentController.index()">Batal</Link>
            </Button>
            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />
                {{
                    state.mode === 'upload'
                        ? 'Simpan dokumen'
                        : 'Simpan dan mulai menyusun'
                }}
            </Button>
        </div>
    </Form>
</template>
