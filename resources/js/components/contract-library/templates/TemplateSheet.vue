<script setup lang="ts">
import { Pencil, Plus } from '@lucide/vue';
import { ref } from 'vue';
import TemplateController from '@/actions/App/Http/Controllers/Templates/TemplateController';
import FieldRow from '@/components/master-data/FieldRow.vue';
import MasterFormSheet from '@/components/master-data/MasterFormSheet.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { LibraryPageInfo, LibraryTemplateRow } from '@/types';

/** The name, description and category of a template. The wording is edited on its page. */
const props = defineProps<{
    info: LibraryPageInfo;
    categories: { value: string; label: string }[];
    row?: LibraryTemplateRow;
}>();

const category = ref<string | null>(
    props.row?.category ? String(props.row.category.id) : null,
);
</script>

<template>
    <MasterFormSheet
        :form="
            row
                ? TemplateController.update.form(row.id)
                : TemplateController.store.form()
        "
        :title="row ? `Ubah ${row.name}` : info.add"
        :description="info.description"
        :trigger-label="row ? 'Ubah' : info.add"
        :trigger-icon="row ? Pencil : Plus"
        :trigger-variant="row ? 'ghost' : 'default'"
        :submit-label="row ? 'Simpan' : 'Buat'"
        :fail-text="info.texts.save_failed"
        v-slot="{ errors }"
    >
        <FieldRow
            id="template-name"
            label="Nama templat"
            :error="errors.name"
            required
        >
            <Input
                id="template-name"
                name="name"
                :default-value="row?.name"
                class="h-11 md:h-9"
                required
            />
        </FieldRow>
        <FieldRow
            id="template-category"
            label="Kategori"
            :error="errors.category_id"
            :help="info.hints.explain"
            required
        >
            <SearchSelect
                id="template-category"
                v-model="category"
                name="category_id"
                label="Kategori"
                placeholder="Pilih kategori"
                :options="categories"
            />
        </FieldRow>
        <FieldRow
            id="template-description"
            label="Keterangan (opsional)"
            :error="errors.description"
        >
            <Textarea
                id="template-description"
                name="description"
                :default-value="row?.description ?? undefined"
                rows="3"
            />
        </FieldRow>
    </MasterFormSheet>
</template>
