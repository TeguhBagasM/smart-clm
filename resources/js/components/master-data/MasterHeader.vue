<script setup lang="ts">
import { Info } from '@lucide/vue';
import CompanyPicker from '@/components/master-data/CompanyPicker.vue';
import Heading from '@/components/Heading.vue';
import { Alert } from '@/components/ui/alert';
import type { MasterCompany } from '@/types';

/** Title, purpose and company of a master data page; the action button goes in the slot. */
defineProps<{
    title: string;
    description: string;
    company?: MasterCompany | null;
    companies?: { value: number; label: string }[] | null;
    notice?: string | null;
}>();
</script>

<template>
    <div class="flex flex-col gap-4">
        <div
            class="flex flex-wrap items-start justify-between gap-3 [&>header]:mb-0"
        >
            <Heading :title="title" :description="description" />
            <div class="flex w-full flex-wrap gap-2 md:w-auto">
                <slot name="action" />
            </div>
        </div>
        <CompanyPicker
            v-if="companies"
            :companies="companies"
            :selected="company?.id ?? null"
        />
        <Alert v-if="notice" class="flex items-start gap-2">
            <Info class="mt-0.5 size-4 shrink-0" />
            <p class="text-sm">{{ notice }}</p>
        </Alert>
    </div>
</template>
