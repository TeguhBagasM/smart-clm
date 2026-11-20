<script setup lang="ts">
import { ref } from 'vue';
import FieldRow from '@/components/master-data/FieldRow.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import { Input } from '@/components/ui/input';
import type { EmployeeFormOptions, EmployeeRow, MasterInfo } from '@/types';

/** The employee form. There is no salary and no national id number anywhere in it. */
const props = defineProps<{
    info: MasterInfo;
    row?: EmployeeRow;
    options: EmployeeFormOptions;
    errors: Record<string, string>;
}>();

const text = (id: number | undefined): string => (id ? String(id) : '');
const department = ref(text(props.row?.department?.id));
const employment = ref(text(props.row?.employment_type?.id));
const account = ref(text(props.row?.user?.id));
const pick = (list: { value: number; label: string }[]) =>
    list.map((o) => ({ value: String(o.value), label: o.label }));
</script>

<template>
    <FieldRow
        id="emp-name"
        label="Nama"
        :help="info.help.name"
        :error="errors.name"
        required
    >
        <Input
            id="emp-name"
            name="name"
            :default-value="row?.name"
            class="h-11 md:h-9"
            required
        />
    </FieldRow>
    <FieldRow id="emp-position" label="Jabatan" :error="errors.position">
        <Input
            id="emp-position"
            name="position"
            :default-value="row?.position ?? undefined"
            class="h-11 md:h-9"
        />
    </FieldRow>
    <FieldRow
        id="emp-number"
        label="Nomor karyawan"
        :help="info.help.employee_number"
        :error="errors.employee_number"
    >
        <Input
            id="emp-number"
            name="employee_number"
            :default-value="row?.employee_number ?? undefined"
            class="h-11 md:h-9"
        />
    </FieldRow>
    <FieldRow
        id="emp-department"
        label="Departemen"
        :help="info.help.department_id"
        :error="errors.department_id"
    >
        <SearchSelect
            id="emp-department"
            v-model="department"
            name="department_id"
            label="Departemen"
            placeholder="Pilih departemen"
            :options="pick(options.departments)"
        />
    </FieldRow>
    <FieldRow
        id="emp-employment"
        label="Status hubungan kerja"
        :help="info.help.employment_type_id"
        :error="errors.employment_type_id"
    >
        <SearchSelect
            id="emp-employment"
            v-model="employment"
            name="employment_type_id"
            label="Status hubungan kerja"
            placeholder="Pilih status"
            :options="pick(options.employment_types)"
        />
    </FieldRow>
    <FieldRow
        id="emp-start"
        label="Tanggal mulai"
        :help="info.help.start_date"
        :error="errors.start_date"
    >
        <Input
            id="emp-start"
            name="start_date"
            type="date"
            :default-value="row?.start_date ?? undefined"
            class="h-11 md:h-9"
        />
    </FieldRow>
    <FieldRow
        v-if="options.users !== null"
        id="emp-user"
        label="Akun pengguna"
        :help="info.help.user_id"
        :error="errors.user_id"
    >
        <SearchSelect
            id="emp-user"
            v-model="account"
            name="user_id"
            label="Akun pengguna"
            placeholder="Tidak dihubungkan"
            :options="pick(options.users)"
        />
    </FieldRow>
</template>
