<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    modelValue: string[];
    placeholder: string;
    label: string;
    max?: number;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: string[]] }>();
const draft = ref('');

function add(): void {
    const value = draft.value.trim();

    if (value && !props.modelValue.includes(value)) {
        emit('update:modelValue', [...props.modelValue, value]);
    }

    draft.value = '';
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <ul v-if="modelValue.length" class="flex flex-wrap gap-2">
            <li v-for="item in modelValue" :key="item">
                <Badge variant="secondary" class="gap-1 py-1 pr-1 text-sm">
                    {{ item }}
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-5"
                        :aria-label="`Hapus ${item}`"
                        @click="
                            emit(
                                'update:modelValue',
                                modelValue.filter((entry) => entry !== item),
                            )
                        "
                    >
                        <X class="size-3" />
                    </Button>
                </Badge>
            </li>
        </ul>
        <div class="flex gap-2">
            <Input
                v-model="draft"
                :placeholder="placeholder"
                :aria-label="label"
                :maxlength="max ?? 100"
                @keydown.enter.prevent="add"
            />
            <Button type="button" variant="outline" @click="add">
                <Plus /> Tambah
            </Button>
        </div>
    </div>
</template>
