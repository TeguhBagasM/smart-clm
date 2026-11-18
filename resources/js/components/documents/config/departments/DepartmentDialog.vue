<script setup lang="ts">
import { Pencil, Plus } from '@lucide/vue';
import DepartmentConfigController from '@/actions/App/Http/Controllers/Documents/Config/DepartmentConfigController';
import ActionDialog from '@/components/documents/actions/ActionDialog.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { DepartmentRow } from '@/types';

/** Adds a department, or renames one (its code cannot change: it is in document numbers). */
defineProps<{ department?: DepartmentRow }>();
</script>

<template>
    <ActionDialog
        :form="
            department
                ? DepartmentConfigController.update.form(department.id)
                : DepartmentConfigController.store.form()
        "
        :trigger-label="department ? 'Ubah' : 'Tambah departemen'"
        :trigger-icon="department ? Pencil : Plus"
        :trigger-variant="department ? 'outline' : 'default'"
        :title="department ? `Ubah ${department.code}` : 'Tambah departemen'"
        :description="
            department
                ? 'Hanya nama yang bisa diubah; kode sudah menjadi bagian nomor dokumen.'
                : 'Kode dipakai pada nomor dokumen dan sebagai folder tempat dokumen disimpan.'
        "
        :submit-label="department ? 'Simpan' : 'Tambah'"
        v-slot="{ errors }"
    >
        <div v-if="!department" class="flex flex-col gap-1.5">
            <Label for="department-code">Kode</Label>
            <Input
                id="department-code"
                name="code"
                maxlength="50"
                placeholder="Mis. HRD"
                class="uppercase"
                required
            />
            <p class="text-xs text-muted-foreground">
                Huruf, angka, spasi, dan _ . & - saja; tanpa garis miring.
            </p>
            <InputError :message="errors.code" />
        </div>
        <div class="flex flex-col gap-1.5">
            <Label for="department-name">Nama</Label>
            <Input
                id="department-name"
                name="name"
                maxlength="150"
                placeholder="Mis. Sumber Daya Manusia"
                :default-value="department?.name"
                required
            />
            <InputError :message="errors.name" />
        </div>
    </ActionDialog>
</template>
