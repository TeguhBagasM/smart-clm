<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed } from 'vue';
import ExternalReviewPageController from '@/actions/App/Http/Controllers/Documents/ExternalReviewPageController';
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
import { Textarea } from '@/components/ui/textarea';
import type { ExternalAnchor, ExternalSection } from '@/types';

const props = defineProps<{
    token: string;
    sections: ExternalSection[];
}>();

const name = defineModel<string>('name', { required: true });
const anchor = defineModel<ExternalAnchor>('anchor', { required: true });

const fields = [
    { value: 'general', label: 'Umum (seluruh dokumen)' },
    { value: 'purpose', label: 'Tujuan' },
    { value: 'scope', label: 'Ruang lingkup' },
    { value: 'section', label: 'Bagian isi' },
];

const heading = computed(() =>
    anchor.value.field === 'section' && anchor.value.sectionIndex !== null
        ? (props.sections[anchor.value.sectionIndex]?.heading ?? '')
        : '',
);

function setField(value: unknown): void {
    const field = String(value) as ExternalAnchor['field'];
    anchor.value = {
        field,
        sectionIndex:
            field === 'section' && props.sections.length > 0
                ? (anchor.value.sectionIndex ?? 0)
                : null,
    };
}
</script>

<template>
    <Form
        v-bind="ExternalReviewPageController.comment.form({ token })"
        reset-on-success
        :only="['review']"
        class="flex flex-col gap-3 rounded-lg border p-4"
        v-slot="{ errors, processing }"
    >
        <h3 id="komentar" class="font-semibold">Tulis komentar</h3>
        <div class="flex flex-col gap-1.5">
            <Label for="reviewer-name">Nama Anda</Label>
            <Input
                id="reviewer-name"
                v-model="name"
                name="name"
                maxlength="80"
                autocomplete="name"
                placeholder="Nama dan instansi Anda"
            />
            <InputError :message="errors.name" />
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="flex flex-col gap-1.5">
                <Label>Bagian yang dimaksud</Label>
                <Select
                    :model-value="anchor.field"
                    @update:model-value="setField"
                >
                    <SelectTrigger class="w-full"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in fields"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div
                v-if="anchor.field === 'section' && sections.length > 0"
                class="flex flex-col gap-1.5"
            >
                <Label>Pilih bagian</Label>
                <Select
                    :model-value="String(anchor.sectionIndex ?? 0)"
                    @update:model-value="
                        (value) =>
                            (anchor = {
                                field: 'section',
                                sectionIndex: Number(value),
                            })
                    "
                >
                    <SelectTrigger class="w-full"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="(section, index) in sections"
                            :key="index"
                            :value="String(index)"
                        >
                            {{ index + 1 }}. {{ section.heading }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>
        <input type="hidden" name="anchor[field]" :value="anchor.field" />
        <template
            v-if="anchor.field === 'section' && anchor.sectionIndex !== null"
        >
            <input
                type="hidden"
                name="anchor[sectionIndex]"
                :value="anchor.sectionIndex"
            />
            <input
                type="hidden"
                name="anchor[sectionHeading]"
                :value="heading"
            />
        </template>
        <div class="flex flex-col gap-1.5">
            <Label for="review-body">Komentar</Label>
            <Textarea
                id="review-body"
                name="body"
                rows="4"
                maxlength="4000"
                placeholder="Tulis apa yang perlu diperbaiki atau dijelaskan"
            />
            <InputError :message="errors.body" />
        </div>
        <Button
            type="submit"
            class="w-full sm:w-auto sm:self-end"
            :disabled="processing"
        >
            Kirim komentar
        </Button>
    </Form>
</template>
