<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Form } from '@inertiajs/vue3';
import type { Component } from 'vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { RouteFormDefinition } from '@/wayfinder';

type Variant = 'default' | 'outline' | 'destructive' | 'secondary' | 'ghost';

withDefaults(
    defineProps<{
        /** The result of a Wayfinder `.form(...)` call. */
        form: RouteFormDefinition<'post'>;
        triggerLabel: string;
        triggerIcon?: Component;
        triggerVariant?: Variant;
        title: string;
        description?: string;
        submitLabel?: string;
        submitVariant?: Variant;
        /** Turns the form data into what is sent (for fields kept in state). */
        transform?: (
            data: Record<string, FormDataConvertible>,
        ) => Record<string, FormDataConvertible>;
    }>(),
    { triggerVariant: 'outline', submitVariant: 'default' },
);

const emit = defineEmits<{ opened: []; done: [] }>();
const open = ref(false);

function onOpen(value: boolean): void {
    open.value = value;
    if (value) emit('opened');
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpen">
        <DialogTrigger as-child>
            <Button :variant="triggerVariant" size="sm">
                <component :is="triggerIcon" v-if="triggerIcon" />
                {{ triggerLabel }}
            </Button>
        </DialogTrigger>
        <DialogContent>
            <Form
                v-bind="form"
                :transform="transform"
                class="flex flex-col gap-4"
                @success="
                    () => {
                        open = false;
                        emit('done');
                    }
                "
                v-slot="{ errors, processing }"
            >
                <DialogHeader>
                    <DialogTitle>{{ title }}</DialogTitle>
                    <DialogDescription v-if="description">
                        {{ description }}
                    </DialogDescription>
                </DialogHeader>
                <slot :errors="errors" :processing="processing" />
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">Batal</Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        :variant="submitVariant"
                        :disabled="processing"
                    >
                        {{ submitLabel ?? triggerLabel }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
