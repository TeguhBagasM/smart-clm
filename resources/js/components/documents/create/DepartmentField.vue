<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ChoiceOption } from '@/types';

const model = defineModel<string>({ required: true });

defineProps<{ options: ChoiceOption[]; error?: string }>();
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <Label for="department">Departemen</Label>
        <Input
            id="department"
            v-model="model"
            list="department-list"
            maxlength="50"
            placeholder="Pilih dari daftar atau ketik departemen baru"
        />
        <datalist id="department-list">
            <option
                v-for="option in options"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </option>
        </datalist>
        <InputError :message="error" />
    </div>
</template>
