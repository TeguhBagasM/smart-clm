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

const done = (step: StepInfo): boolean =>
    Boolean(step.done && props.completeness[step.done]);
const passed = (index: number): boolean =>
    index < props.steps.findIndex((step) => step.key === props.current);

/** On a phone the timeline scrolls sideways; keep the current step visible. */
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
        class="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0"
    >
        <ol class="flex min-w-max items-center gap-2 md:min-w-0">
            <template v-for="(step, index) in steps" :key="step.key">
                <li
                    v-if="index > 0"
                    aria-hidden="true"
                    :class="[
                        'h-px min-w-4 flex-1',
                        passed(index) ? 'bg-primary' : 'bg-border',
                    ]"
                />
                <li class="shrink-0">
                    <button
                        type="button"
                        :aria-current="
                            step.key === current ? 'step' : undefined
                        "
                        :title="step.hint"
                        :class="[
                            'flex items-center gap-2 rounded-full py-1 pr-3 pl-1 text-sm transition-colors hover:bg-accent',
                            step.key === current
                                ? 'font-medium text-primary'
                                : 'text-muted-foreground',
                        ]"
                        @click="$emit('go', step.key)"
                    >
                        <span
                            :class="[
                                'flex size-7 shrink-0 items-center justify-center rounded-full border text-xs font-medium',
                                done(step)
                                    ? 'border-success bg-success text-success-foreground'
                                    : step.key === current
                                      ? 'border-primary ring-2 ring-ring/70'
                                      : '',
                            ]"
                        >
                            <Check v-if="done(step)" class="size-3.5" />
                            <template v-else>{{ index + 1 }}</template>
                        </span>
                        <span class="whitespace-nowrap">{{ step.label }}</span>
                    </button>
                </li>
            </template>
        </ol>
    </nav>
</template>
