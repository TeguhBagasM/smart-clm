<script setup lang="ts">
import { ref } from 'vue';
import FieldRow from '@/components/master-data/FieldRow.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { LibraryFieldRow, LibraryPageInfo } from '@/types';

/**
 * The field form. The code is what clauses write between {{ and }}: letters and
 * digits only, starting with a letter. A code in use cannot change (the server says so).
 */
const props = defineProps<{
    row?: LibraryFieldRow;
    info: LibraryPageInfo;
    errors: Record<string, string>;
}>();

const type = ref<string | null>(props.row?.value_type.value ?? 'text');
const source = ref<string | null>(props.row?.source_field ?? null);
</script>

<template>
    <FieldRow
        id="field-code"
        label="Kode"
        :error="errors.code"
        :help="info.hints.explain"
        required
    >
        <Input
            id="field-code"
            name="code"
            :default-value="row?.code"
            placeholder="NamaPerusahaan"
            autocomplete="off"
            class="h-11 font-mono md:h-9"
            required
        />
    </FieldRow>
    <FieldRow id="field-name" label="Nama" :error="errors.name" required>
        <Input
            id="field-name"
            name="name"
            :default-value="row?.name"
            class="h-11 md:h-9"
            required
        />
    </FieldRow>
    <FieldRow
        id="field-type"
        label="Jenis isian"
        :error="errors.value_type"
        required
    >
        <SearchSelect
            id="field-type"
            v-model="type"
            name="value_type"
            label="Jenis isian"
            placeholder="Pilih jenis"
            :options="info.types ?? []"
        />
    </FieldRow>
    <FieldRow
        id="field-source"
        label="Diisi otomatis dari"
        :error="errors.source_field"
    >
        <SearchSelect
            id="field-source"
            v-model="source"
            name="source_field"
            label="Diisi otomatis dari"
            placeholder="Diisi manual saat kontrak dibuat"
            :options="info.sources ?? []"
        />
    </FieldRow>
    <FieldRow
        id="field-default"
        label="Nilai awal (opsional)"
        :error="errors.default_value"
    >
        <Input
            id="field-default"
            name="default_value"
            :default-value="row?.default_value ?? undefined"
            class="h-11 md:h-9"
        />
    </FieldRow>
    <FieldRow
        id="field-description"
        label="Keterangan (opsional)"
        :error="errors.description"
    >
        <Textarea
            id="field-description"
            name="description"
            :default-value="row?.description ?? undefined"
            rows="2"
        />
    </FieldRow>
</template>
