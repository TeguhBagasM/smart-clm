<script setup lang="ts">
import { ref } from 'vue';
import ClauseAssist from '@/components/contract-library/clauses/ClauseAssist.vue';
import FieldRow from '@/components/master-data/FieldRow.vue';
import PlaceholderHint from '@/components/contract-library/PlaceholderHint.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { LibraryClauseRow, LibraryPageInfo } from '@/types';

/**
 * The clause form. The assistant can fill the title and the text; both stay
 * editable. Field names of the text are checked here as the person types, and by
 * the server when saving.
 */
const props = defineProps<{
    row?: LibraryClauseRow;
    info: LibraryPageInfo;
    categories: { value: string; label: string }[];
    fieldCodes: string[];
    errors: Record<string, string>;
}>();

const title = ref(props.row?.title ?? '');
const body = ref(props.row?.body ?? '');
const category = ref<string | null>(
    props.row?.category ? String(props.row.category.id) : null,
);

function applyDraft(draft: { title: string; body: string }): void {
    title.value = draft.title;
    body.value = draft.body;
}
</script>

<template>
    <FieldRow
        id="clause-title"
        label="Judul pasal"
        :error="errors.title"
        required
    >
        <Input
            id="clause-title"
            v-model="title"
            name="title"
            required
            class="h-11 md:h-9"
        />
    </FieldRow>
    <FieldRow id="clause-category" label="Kategori" :error="errors.category_id">
        <SearchSelect
            id="clause-category"
            v-model="category"
            name="category_id"
            label="Kategori"
            placeholder="Pilih kategori"
            :options="categories"
        />
    </FieldRow>
    <FieldRow id="clause-body" label="Isi pasal" :error="errors.body" required>
        <Textarea
            id="clause-body"
            v-model="body"
            name="body"
            rows="8"
            required
            class="min-h-40"
        />
        <PlaceholderHint
            :text="body"
            :known-fields="fieldCodes"
            :system-tokens="info.system_tokens"
            :labels="info.hints"
        />
    </FieldRow>
    <div class="flex flex-col gap-3 rounded-lg border p-3">
        <label class="flex min-h-11 cursor-pointer items-center gap-3 text-sm">
            <Checkbox
                name="is_mandatory"
                value="1"
                :default-value="row?.is_mandatory ?? false"
            />
            Wajib ada di setiap kontrak
        </label>
        <label class="flex min-h-11 cursor-pointer items-center gap-3 text-sm">
            <Checkbox
                name="is_protected"
                value="1"
                :default-value="row?.is_protected ?? false"
            />
            Terlindungi: hanya tim Legal yang boleh mengubah
        </label>
    </div>
    <ClauseAssist
        :categories="categories"
        :texts="info.assist"
        @draft="applyDraft"
    />
</template>
