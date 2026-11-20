<script setup lang="ts">
import PlaceholderHint from '@/components/contract-library/PlaceholderHint.vue';
import FieldRow from '@/components/master-data/FieldRow.vue';
import { Textarea } from '@/components/ui/textarea';

/** The three paragraphs of the template: opening, closing, and the addendum recital. */
const opening = defineModel<string>('opening', { required: true });
const closing = defineModel<string>('closing', { required: true });
const addendum = defineModel<string>('addendum', { required: true });

defineProps<{
    labels: Record<string, string>;
    hints: { explain: string; unknown: string; ok: string };
    knownFields: string[];
    systemTokens: string[];
}>();
</script>

<template>
    <section
        class="flex flex-col gap-4"
        aria-label="Kalimat pembuka dan penutup"
    >
        <FieldRow
            id="draft-opening"
            :label="labels.opening"
            :help="labels.paragraph_help"
        >
            <Textarea
                id="draft-opening"
                v-model="opening"
                rows="3"
                class="min-h-20"
            />
            <PlaceholderHint
                :text="opening"
                :known-fields="knownFields"
                :system-tokens="systemTokens"
                :labels="hints"
            />
        </FieldRow>
        <FieldRow id="draft-closing" :label="labels.closing">
            <Textarea
                id="draft-closing"
                v-model="closing"
                rows="2"
                class="min-h-16"
            />
            <PlaceholderHint
                :text="closing"
                :known-fields="knownFields"
                :system-tokens="systemTokens"
                :labels="hints"
            />
        </FieldRow>
        <FieldRow id="draft-addendum" :label="labels.addendum">
            <Textarea
                id="draft-addendum"
                v-model="addendum"
                rows="2"
                class="min-h-16"
            />
            <PlaceholderHint
                :text="addendum"
                :known-fields="knownFields"
                :system-tokens="systemTokens"
                :labels="hints"
            />
        </FieldRow>
    </section>
</template>
