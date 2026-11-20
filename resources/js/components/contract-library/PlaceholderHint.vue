<script setup lang="ts">
import { AlertTriangle, Info } from '@lucide/vue';
import { computed } from 'vue';
import { placeholderCodes, placeholderText } from '@/lib/contractLibrary';

/**
 * Says, in plain words, which {{Nama}} a text uses, and which of them the company
 * has no field for. The server checks again when saving.
 */
const props = defineProps<{
    text: string;
    knownFields: string[];
    systemTokens: string[];
    labels: { explain: string; unknown: string; ok: string };
}>();

const codes = computed(() => placeholderCodes(props.text));
const unknown = computed(() =>
    codes.value.filter(
        (code) =>
            !props.knownFields.includes(code) &&
            !props.systemTokens.includes(code),
    ),
);
</script>

<template>
    <div v-if="codes.length > 0" class="flex flex-col gap-2 text-sm">
        <p class="flex items-start gap-2 text-muted-foreground">
            <Info class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <span>{{ labels.explain }}</span>
        </p>
        <p
            v-if="unknown.length > 0"
            class="flex items-start gap-2 text-warning-foreground"
        >
            <AlertTriangle class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <span>
                {{ labels.unknown }}
                <strong>{{ unknown.map(placeholderText).join(', ') }}</strong>
            </span>
        </p>
        <p v-else class="text-muted-foreground">{{ labels.ok }}</p>
    </div>
</template>
