<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import DocumentController from '@/actions/App/Http/Controllers/Documents/DocumentController';
import SaveStatus from '@/components/documents/wizard/SaveStatus.vue';
import WizardStepper from '@/components/documents/wizard/WizardStepper.vue';
import { steps } from '@/components/documents/wizard/steps';
import type { StepKey } from '@/components/documents/wizard/steps';
import { Button } from '@/components/ui/button';
import type { DraftStatus } from '@/composables/useDraftWizard';
import { versionLabel } from '@/lib/format';
import type { DocumentDetail, Draft, WizardVersion } from '@/types';

const props = defineProps<{
    document: DocumentDetail;
    version: WizardVersion;
    draft: Draft;
    status: DraftStatus;
    step: StepKey;
}>();

const emit = defineEmits<{ go: [step: StepKey] }>();

const index = computed(() =>
    steps.findIndex((item) => item.key === props.step),
);
const current = computed(() => steps[index.value]);

function move(by: -1 | 1): void {
    emit('go', steps[index.value + by].key);
}
</script>

<template>
    <div class="flex flex-col gap-5 p-4 md:p-6">
        <header class="flex flex-col gap-2">
            <Link
                :href="DocumentController.show({ document: document.id })"
                class="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-4" /> Keluar (draft tersimpan)
            </Link>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <span class="font-mono text-sm text-muted-foreground">
                        {{ document.number }} · {{ versionLabel(version) }}
                    </span>
                    <h1 class="text-xl font-semibold tracking-tight">
                        {{ document.title }}
                    </h1>
                </div>
                <SaveStatus :status="status" />
            </div>
        </header>

        <WizardStepper
            :steps="steps"
            :current="step"
            :completeness="draft.completeness"
            @go="emit('go', $event)"
        />
        <section
            class="flex min-w-0 flex-col gap-5"
            :aria-label="current.label"
        >
            <div>
                <h2 class="text-lg font-semibold">
                    {{ index + 1 }}. {{ current.label }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ current.hint }}
                </p>
            </div>
            <slot />
            <div
                class="sticky bottom-0 z-10 -mx-4 flex justify-between gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur md:-mx-6 md:px-6"
            >
                <Button
                    variant="outline"
                    :disabled="index === 0"
                    @click="move(-1)"
                >
                    <ArrowLeft /> Sebelumnya
                </Button>
                <Button v-if="index < steps.length - 1" @click="move(1)">
                    Berikutnya <ArrowRight />
                </Button>
            </div>
        </section>
    </div>
</template>
