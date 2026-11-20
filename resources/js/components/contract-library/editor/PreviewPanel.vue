<script setup lang="ts">
import { RefreshCw } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import TemplateDraftController from '@/actions/App/Http/Controllers/Templates/TemplateDraftController';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { libraryRead } from '@/lib/libraryRequest';
import type { LibraryPreview, LibraryPreviewSegment } from '@/types';

/**
 * The template as a reader sees it, with sample values. Every part is shown as
 * text; nothing from the company is turned into markup here.
 */
const props = defineProps<{
    templateId: number;
    labels: Record<string, string>;
    failText: string;
}>();

const preview = ref<LibraryPreview | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);

function isField(
    segment: LibraryPreviewSegment,
): segment is { code: string; value: string; missing: boolean } {
    return 'code' in segment;
}

async function load(): Promise<void> {
    if (loading.value) return;

    loading.value = true;
    error.value = null;

    const result = await libraryRead<LibraryPreview>(
        TemplateDraftController.preview(props.templateId).url,
        props.failText,
    );

    loading.value = false;

    if (result.ok) {
        preview.value = result.data;
    } else {
        error.value = result.message;
    }
}

onMounted(load);
</script>

<template>
    <section class="flex flex-col gap-4" :aria-label="labels.title">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-muted-foreground">{{ labels.note }}</p>
            <Button
                type="button"
                variant="outline"
                class="h-11 md:h-9"
                :disabled="loading"
                @click="load"
            >
                <RefreshCw />{{ labels.refresh }}
            </Button>
        </div>
        <Alert v-if="error" variant="destructive"
            ><AlertDescription>{{ error }}</AlertDescription></Alert
        >
        <p
            v-else-if="!preview && !loading"
            class="text-sm text-muted-foreground"
        >
            {{ labels.empty }}
        </p>
        <article
            v-if="preview"
            class="flex flex-col gap-4 rounded-xl border bg-background p-4 text-sm leading-relaxed md:p-6"
        >
            <p v-if="preview.opening" class="whitespace-pre-line">
                <template v-for="(part, index) in preview.opening" :key="index">
                    <span v-if="!isField(part)">{{ part.text }}</span>
                    <span
                        v-else
                        :class="
                            part.missing
                                ? 'text-warning-foreground'
                                : 'font-semibold'
                        "
                        >{{ part.value }}</span
                    >
                </template>
            </p>
            <ol class="flex flex-col gap-3">
                <li
                    v-for="(clause, index) in preview.clauses"
                    :key="index"
                    class="flex flex-col gap-1"
                >
                    <h4 class="font-semibold">
                        {{ index + 1 }}. {{ clause.title }}
                    </h4>
                    <p class="whitespace-pre-line">
                        <template
                            v-for="(part, partIndex) in clause.segments"
                            :key="partIndex"
                        >
                            <span v-if="!isField(part)">{{ part.text }}</span>
                            <span
                                v-else
                                :class="
                                    part.missing
                                        ? 'text-warning-foreground'
                                        : 'font-semibold'
                                "
                                >{{ part.value }}</span
                            >
                        </template>
                    </p>
                </li>
            </ol>
            <p v-if="preview.closing" class="whitespace-pre-line">
                <template v-for="(part, index) in preview.closing" :key="index">
                    <span v-if="!isField(part)">{{ part.text }}</span>
                    <span
                        v-else
                        :class="
                            part.missing
                                ? 'text-warning-foreground'
                                : 'font-semibold'
                        "
                        >{{ part.value }}</span
                    >
                </template>
            </p>
        </article>
    </section>
</template>
