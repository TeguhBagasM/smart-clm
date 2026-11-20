<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import type { Component } from 'vue';
import { ref } from 'vue';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import type { RouteFormDefinition } from '@/wayfinder';

/**
 * Add or change a row in a side sheet (full screen on a phone). The fields go
 * in the slot. Nothing the person typed is lost when saving fails: errors show
 * under the fields, and a lost connection or an expired session shows `failText`
 * while the fields stay as they were. The button is off while a request runs.
 */
withDefaults(
    defineProps<{
        form: RouteFormDefinition<'post'>;
        title: string;
        description?: string;
        triggerLabel: string;
        triggerIcon?: Component;
        triggerVariant?: 'default' | 'outline' | 'ghost';
        submitLabel: string;
        failText: string;
        /** The company the row belongs to (only a super admin's choice is used by the server). */
        companyId?: number | null;
    }>(),
    { triggerVariant: 'default' },
);

const open = ref(false);
const failed = ref(false);
</script>

<template>
    <Sheet v-model:open="open">
        <SheetTrigger as-child>
            <Button
                :variant="triggerVariant"
                class="h-11 w-full md:h-9 md:w-auto"
            >
                <component :is="triggerIcon" v-if="triggerIcon" />
                {{ triggerLabel }}
            </Button>
        </SheetTrigger>
        <SheetContent class="w-full gap-0 sm:max-w-lg">
            <Form
                v-bind="form"
                class="flex h-full min-h-0 flex-col"
                @start="failed = false"
                @success="open = false"
                @network-error="failed = true"
                @http-exception="failed = true"
                v-slot="{ errors, processing }"
            >
                <SheetHeader class="border-b">
                    <SheetTitle>{{ title }}</SheetTitle>
                    <SheetDescription v-if="description">
                        {{ description }}
                    </SheetDescription>
                </SheetHeader>
                <div class="flex flex-1 flex-col gap-4 overflow-y-auto p-4">
                    <Alert v-if="failed" variant="destructive">
                        <p class="text-sm">{{ failText }}</p>
                    </Alert>
                    <input
                        v-if="companyId"
                        type="hidden"
                        name="tenant_id"
                        :value="companyId"
                    />
                    <slot :errors="errors" />
                </div>
                <div
                    class="flex flex-col-reverse gap-2 border-t p-4 sm:flex-row sm:justify-end"
                >
                    <Button
                        type="button"
                        variant="secondary"
                        class="h-11 sm:h-9"
                        @click="open = false"
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        class="h-11 sm:h-9"
                        :disabled="processing"
                    >
                        {{ processing ? 'Menyimpan…' : submitLabel }}
                    </Button>
                </div>
            </Form>
        </SheetContent>
    </Sheet>
</template>
