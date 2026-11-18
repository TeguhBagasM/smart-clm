<script setup lang="ts">
import { computed, ref } from 'vue';
import Panel from '@/components/documents/Panel.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ChoiceOption } from '@/types';

const model = defineModel<string[]>({ required: true });

const props = withDefaults(
    defineProps<{
        options: ChoiceOption[];
        title?: string;
        description?: string;
    }>(),
    {
        title: 'Klausul bawaan',
        description: 'Klausul yang otomatis disertakan pada dokumen jenis ini.',
    },
);
const term = ref('');

const groups = computed(() => {
    const visible = props.options.filter((option) =>
        option.label.toLowerCase().includes(term.value.toLowerCase()),
    );

    return Object.entries(
        Object.groupBy(visible, (option) => option.group ?? 'Lainnya'),
    );
});

function toggle(value: string, on: boolean): void {
    model.value = on
        ? [...model.value, value]
        : model.value.filter((item) => item !== value);
}
</script>

<template>
    <Panel :title="title" :count="model.length">
        <p class="mb-3 text-sm text-muted-foreground">
            {{ description }}
        </p>
        <p v-if="options.length === 0" class="text-sm text-muted-foreground">
            Belum ada klausul aktif. Tambahkan dulu di tab Klausul.
        </p>
        <template v-else>
            <Input
                v-model="term"
                type="search"
                placeholder="Cari klausul"
                class="mb-3"
            />
            <div class="flex max-h-72 flex-col gap-4 overflow-y-auto">
                <fieldset v-for="[group, items] in groups" :key="group">
                    <legend
                        class="mb-1.5 text-xs font-medium text-muted-foreground uppercase"
                    >
                        {{ group }}
                    </legend>
                    <div class="flex flex-col gap-2">
                        <Label
                            v-for="option in items"
                            :key="option.value"
                            class="flex items-center gap-2 font-normal"
                        >
                            <Checkbox
                                :model-value="model.includes(option.value)"
                                @update:model-value="
                                    toggle(option.value, $event === true)
                                "
                            />
                            {{ option.label }}
                        </Label>
                    </div>
                </fieldset>
            </div>
        </template>
    </Panel>
</template>
