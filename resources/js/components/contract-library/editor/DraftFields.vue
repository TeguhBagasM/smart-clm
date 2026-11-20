<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Checkbox } from '@/components/ui/checkbox';
import { placeholderText } from '@/lib/contractLibrary';
import type { DraftState } from '@/lib/contractLibrary';
import type { LibraryFieldOption } from '@/types';

/** The fields the template asks for when a contract is made, and which of them must be filled. */
const chosen = defineModel<DraftState['fields']>({ required: true });

defineProps<{
    options: LibraryFieldOption[];
    labels: Record<string, string>;
    missing: string[];
}>();

const isChosen = (id: number): boolean =>
    chosen.value.some((field) => field.id === id);

function toggle(id: number, on: boolean): void {
    chosen.value = on
        ? [...chosen.value, { id, required: true }]
        : chosen.value.filter((field) => field.id !== id);
}

function setRequired(id: number, required: boolean): void {
    chosen.value = chosen.value.map((field) =>
        field.id === id ? { ...field, required } : field,
    );
}
</script>

<template>
    <section class="flex flex-col gap-3" :aria-label="labels.fields">
        <h3 class="text-sm font-semibold">{{ labels.fields }}</h3>
        <Alert v-if="missing.length > 0" variant="destructive">
            <AlertDescription>
                {{ labels.field_missing }}
                <strong>{{ missing.map(placeholderText).join(', ') }}</strong>
            </AlertDescription>
        </Alert>
        <ul class="flex flex-col gap-2">
            <li
                v-for="field in options"
                :key="field.value"
                class="flex flex-col gap-1 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <label
                    class="flex min-h-11 cursor-pointer items-center gap-3 text-sm"
                >
                    <Checkbox
                        :model-value="isChosen(field.value)"
                        @update:model-value="
                            toggle(field.value, $event === true)
                        "
                    />
                    <span>
                        <span class="font-medium">{{ field.label }}</span>
                        <code class="ml-2 text-xs text-muted-foreground">{{
                            placeholderText(field.code)
                        }}</code>
                    </span>
                </label>
                <label
                    v-if="isChosen(field.value)"
                    class="flex min-h-11 cursor-pointer items-center gap-2 text-sm"
                >
                    <Checkbox
                        :model-value="
                            chosen.find((item) => item.id === field.value)
                                ?.required ?? false
                        "
                        @update:model-value="
                            setRequired(field.value, $event === true)
                        "
                    />
                    {{ labels.field_required }}
                </label>
            </li>
        </ul>
        <p v-if="options.length === 0" class="text-sm text-muted-foreground">
            {{ labels.fields_empty }}
        </p>
    </section>
</template>
