<script setup lang="ts">
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

defineProps<{ error?: string }>();

const field = ref('general');

const fields = [
    { value: 'general', label: 'Umum (seluruh dokumen)' },
    { value: 'purpose', label: 'Tujuan' },
    { value: 'scope', label: 'Ruang lingkup' },
    { value: 'section', label: 'Bagian isi' },
    { value: 'flow', label: 'Diagram alir' },
];
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-2">
        <div class="flex flex-col gap-1.5">
            <Label>Bagian yang dimaksud</Label>
            <Select v-model="field" name="anchor[field]">
                <SelectTrigger class="w-full">
                    <SelectValue />
                </SelectTrigger>
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
        <div class="flex flex-col gap-1.5">
            <Label for="anchor-quote">Kutipan (opsional)</Label>
            <Input
                id="anchor-quote"
                name="anchor[quote]"
                maxlength="500"
                placeholder="Teks yang dimaksud"
            />
        </div>
        <InputError v-if="error" :message="error" />
    </div>
</template>
