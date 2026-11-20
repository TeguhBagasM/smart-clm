<script setup lang="ts">
import { Sparkles } from '@lucide/vue';
import { ref } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import ClauseDraftController from '@/actions/App/Http/Controllers/Templates/ClauseDraftController';
import { libraryRequest } from '@/lib/libraryRequest';
import type { LibraryAiDraft } from '@/types';

/**
 * The clause assistant: a description goes in, a suggestion comes back and fills
 * the form. It does not save. Warnings about the suggestion are shown with it.
 */
const props = defineProps<{
    texts: Record<string, string>;
}>();

const emit = defineEmits<{ draft: [draft: LibraryAiDraft] }>();

const description = ref('');
const loading = ref(false);
const error = ref<string | null>(null);
const warnings = ref<string[]>([]);
const applied = ref(false);

async function ask(): Promise<void> {
    if (loading.value || description.value.trim() === '') return;

    loading.value = true;
    error.value = null;
    applied.value = false;

    const result = await libraryRequest<LibraryAiDraft>(
        'POST',
        ClauseDraftController.store().url,
        { description: description.value },
        'Saran AI belum bisa dipakai sekarang. Tulis pasal secara manual, atau coba lagi beberapa saat lagi.',
    );

    loading.value = false;

    if (!result.ok) {
        error.value = result.errors.description ?? result.message;
        return;
    }

    warnings.value = result.data.warnings;
    applied.value = true;
    emit('draft', result.data);
}
</script>

<template>
    <details class="rounded-lg border bg-muted/40 p-3" open>
        <summary
            class="flex min-h-11 cursor-pointer items-center gap-2 text-sm font-medium"
        >
            <Sparkles class="size-4" aria-hidden="true" />
            {{ texts.title }}
        </summary>
        <div class="mt-3 flex flex-col gap-3">
            <label for="assist-description" class="text-sm font-medium">{{
                texts.description_label
            }}</label>
            <Textarea
                id="assist-description"
                v-model="description"
                rows="2"
                maxlength="1000"
                class="min-h-16"
                :aria-describedby="'assist-help'"
            />
            <p id="assist-help" class="text-xs text-muted-foreground">
                {{ texts.description_help }}
            </p>
            <Button
                type="button"
                variant="secondary"
                class="h-11 w-full md:h-9 md:w-auto"
                :disabled="loading || description.trim() === ''"
                @click="ask"
            >
                {{ loading ? texts.loading : texts.button }}
            </Button>
            <Alert v-if="error" variant="destructive"
                ><AlertDescription>{{ error }}</AlertDescription></Alert
            >
            <div
                v-if="applied && warnings.length > 0"
                class="flex flex-col gap-1 text-sm text-warning-foreground"
            >
                <p class="font-medium">{{ texts.warnings }}</p>
                <ul class="list-disc pl-5">
                    <li v-for="warning in warnings" :key="warning">
                        {{ warning }}
                    </li>
                </ul>
            </div>
            <p v-else-if="applied" class="text-sm text-muted-foreground">
                {{ texts.applied }}
            </p>
        </div>
    </details>
</template>
