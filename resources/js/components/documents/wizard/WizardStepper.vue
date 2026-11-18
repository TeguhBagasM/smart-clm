<script setup lang="ts">
import { Check } from '@lucide/vue';
import { nextTick, ref, watchEffect } from 'vue';
import type { StepInfo, StepKey } from '@/components/documents/wizard/steps';
import type { Draft } from '@/types';

const props = defineProps<{
    steps: StepInfo[];
    current: StepKey;
    completeness: Draft['completeness'];
}>();

defineEmits<{ go: [step: StepKey] }>();

const nav = ref<HTMLElement | null>(null);

/** On a phone the list scrolls sideways; keep the current step visible. */
watchEffect(() => {
    void props.current;
    void nextTick(() =>
        nav.value
            ?.querySelector('[aria-current="step"]')
            ?.scrollIntoView({ inline: 'center', block: 'nearest' }),
    );
});
</script>

<template>
    <nav
        ref="nav"
        aria-label="Langkah menyusun dokumen"
        class="-mx-4 overflow-x-auto px-4 lg:mx-0 lg:px-0"
    >
        <ol class="flex w-max gap-2 lg:w-auto lg:flex-col">
            <li v-for="(step, index) in steps" :key="step.key">
                <button
                    type="button"
                    :aria-current="step.key === current ? 'step' : undefined"
                    :class="[
                        'flex w-full items-center gap-3 rounded-lg border px-3 py-2 text-left text-sm transition-colors',
                        step.key === current
                            ? 'border-primary bg-accent'
                            : 'border-transparent hover:bg-accent/60',
                    ]"
                    @click="$emit('go', step.key)"
                >
                    <span
                        :class="[
                            'flex size-6 shrink-0 items-center justify-center rounded-full border text-xs font-medium',
                            step.done && completeness[step.done]
                                ? 'border-success bg-success text-success-foreground'
                                : 'text-muted-foreground',
                        ]"
                    >
                        <Check
                            v-if="step.done && completeness[step.done]"
                            class="size-3.5"
                        />
                        <template v-else>{{ index + 1 }}</template>
                    </span>
                    <span class="flex flex-col">
                        <span class="font-medium whitespace-nowrap">{{
                            step.label
                        }}</span>
                        <span
                            class="hidden text-xs text-muted-foreground lg:block"
                        >
                            {{ step.hint }}
                        </span>
                    </span>
                </button>
            </li>
        </ol>
    </nav>
</template>
