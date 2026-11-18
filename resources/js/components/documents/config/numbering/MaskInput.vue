<script setup lang="ts">
import { useDebounceFn } from '@vueuse/core';
import { ref, watch } from 'vue';
import NumberingConfigController from '@/actions/App/Http/Controllers/Documents/Config/NumberingConfigController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';

const mask = defineModel<string>({ required: true });

const props = defineProps<{
    tokens: string[];
    typeId?: string;
    error?: string;
}>();

const example = ref<string | null>(null);
const problem = ref<string | null>(null);

const check = useDebounceFn(async () => {
    const { url } = NumberingConfigController.preview({
        query: {
            mask: mask.value,
            ...(props.typeId ? { type: props.typeId } : {}),
        },
    });
    const answer = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });
    const body = (await answer.json()) as {
        example: string | null;
        error: string | null;
    };
    example.value = body.example;
    problem.value = body.error;
}, 300);

watch(mask, () => void check(), { immediate: true });
</script>

<template>
    <div class="flex flex-col gap-3">
        <Input
            v-model="mask"
            name="mask"
            maxlength="200"
            class="font-mono"
            aria-label="Format nomor"
            required
        />
        <div class="flex flex-wrap gap-1.5" aria-label="Sisipkan token">
            <button
                v-for="token in tokens"
                :key="token"
                type="button"
                @click="mask += token"
            >
                <Badge variant="outline" class="font-mono hover:bg-accent">
                    {{ token }}
                </Badge>
            </button>
        </div>
        <div class="rounded-md bg-muted px-3 py-2 text-sm">
            <span class="text-muted-foreground">Contoh nomor pertama: </span>
            <span v-if="example" class="font-mono font-medium">{{
                example
            }}</span>
            <span v-else class="text-destructive">
                {{ problem ?? '…' }}
            </span>
        </div>
        <InputError :message="error" />
    </div>
</template>
