<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';

/** One form field: label, the control (slot), the help text from the server and the error. */
defineProps<{
    id: string;
    label: string;
    help?: string;
    error?: string;
    required?: boolean;
}>();
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <Label :for="id">
            {{ label }}
            <span v-if="required" class="text-destructive" aria-hidden="true">
                *
            </span>
        </Label>
        <slot />
        <p v-if="help" :id="`${id}-help`" class="text-xs text-muted-foreground">
            {{ help }}
        </p>
        <InputError :message="error" />
    </div>
</template>
