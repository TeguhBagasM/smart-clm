<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { reactive } from 'vue';
import ClauseConfigController from '@/actions/App/Http/Controllers/Documents/Config/ClauseConfigController';
import Panel from '@/components/documents/Panel.vue';
import FormActions from '@/components/documents/config/FormActions.vue';
import StringListEditor from '@/components/documents/config/StringListEditor.vue';
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { ChoiceOption, ClauseForm } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

const props = defineProps<{
    action: RouteFormDefinition<'post'>;
    submitLabel: string;
    clause?: ClauseForm;
    typeOptions: ChoiceOption[];
    categoryOptions: string[];
}>();

const state = reactive({
    title: props.clause?.title ?? '',
    content: props.clause?.content ?? '',
    category: props.clause?.category ?? 'General',
    isMandatory: props.clause?.isMandatory ?? false,
    tags: props.clause?.tags ?? [],
    types: props.clause?.applicableDocTypeCodes ?? [],
});

function toggleType(code: string, on: boolean): void {
    state.types = on
        ? [...state.types, code]
        : state.types.filter((item) => item !== code);
}

function payload(): Record<string, never> {
    return {
        title: state.title,
        content: state.content,
        category: state.category,
        isMandatory: state.isMandatory,
        tags: state.tags,
        applicableDocTypeCodes: state.types,
    } as never;
}
</script>

<template>
    <Form
        v-bind="action"
        :transform="payload"
        class="flex flex-col gap-6"
        v-slot="{ errors, processing }"
    >
        <Panel title="Isi klausul">
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-1.5">
                    <Label for="clause-title">Judul</Label>
                    <Input
                        id="clause-title"
                        v-model="state.title"
                        maxlength="200"
                        placeholder="Mis. Kerahasiaan informasi"
                    />
                    <InputError :message="errors.title" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label for="clause-content">Isi</Label>
                    <Textarea
                        id="clause-content"
                        v-model="state.content"
                        rows="8"
                        placeholder="Teks klausul yang akan disalin ke dokumen"
                    />
                    <InputError :message="errors.content" />
                </div>
            </div>
        </Panel>
        <Panel title="Pengelompokan">
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-1.5">
                    <Label for="clause-category">Kategori</Label>
                    <Input
                        id="clause-category"
                        v-model="state.category"
                        list="clause-categories"
                        maxlength="100"
                    />
                    <datalist id="clause-categories">
                        <option
                            v-for="category in categoryOptions"
                            :key="category"
                            :value="category"
                        />
                    </datalist>
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label>Tag</Label>
                    <StringListEditor
                        v-model="state.tags"
                        label="Tag"
                        placeholder="Mis. iso"
                        :max="50"
                    />
                </div>
                <Label class="flex items-center gap-2 font-normal">
                    <Checkbox
                        :model-value="state.isMandatory"
                        @update:model-value="
                            state.isMandatory = $event === true
                        "
                    />
                    Klausul wajib (tidak boleh dihapus penyusun dari dokumen)
                </Label>
            </div>
        </Panel>
        <Panel title="Berlaku untuk jenis dokumen">
            <p class="mb-3 text-sm text-muted-foreground">
                Tidak memilih apa pun berarti berlaku untuk semua jenis dokumen.
            </p>
            <div class="grid gap-2 sm:grid-cols-2">
                <Label
                    v-for="option in typeOptions"
                    :key="option.value"
                    class="flex items-center gap-2 font-normal"
                >
                    <Checkbox
                        :model-value="state.types.includes(option.value)"
                        @update:model-value="
                            toggleType(option.value, $event === true)
                        "
                    />
                    {{ option.label }}
                </Label>
            </div>
            <InputError :message="errors.applicableDocTypeCodes" />
        </Panel>
        <FormActions
            :cancel-href="ClauseConfigController.index()"
            :processing="processing"
            :submit-label="submitLabel"
        />
    </Form>
</template>
