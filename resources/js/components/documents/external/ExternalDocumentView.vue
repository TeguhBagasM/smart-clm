<script setup lang="ts">
import { MessageSquarePlus } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { ExternalAnchor, ExternalReviewData } from '@/types';

defineProps<{
    document: ExternalReviewData['document'];
    canComment: boolean;
}>();

defineEmits<{ comment: [anchor: ExternalAnchor] }>();
</script>

<template>
    <div class="flex flex-col gap-6">
        <section
            v-for="block in [
                {
                    field: 'purpose' as const,
                    title: 'Tujuan',
                    text: document.purpose,
                },
                {
                    field: 'scope' as const,
                    title: 'Ruang lingkup',
                    text: document.scope,
                },
            ].filter((item) => item.text !== '')"
            :key="block.field"
            class="flex flex-col gap-2"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-lg font-semibold">{{ block.title }}</h2>
                <Button
                    v-if="canComment"
                    variant="ghost"
                    size="sm"
                    @click="
                        $emit('comment', {
                            field: block.field,
                            sectionIndex: null,
                        })
                    "
                >
                    <MessageSquarePlus /> Komentari
                </Button>
            </div>
            <p class="text-sm leading-relaxed whitespace-pre-wrap">
                {{ block.text }}
            </p>
        </section>

        <section
            v-for="(section, index) in document.sections"
            :key="index"
            class="flex flex-col gap-2"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-lg font-semibold">
                    {{ index + 1 }}. {{ section.heading }}
                </h2>
                <Button
                    v-if="canComment"
                    variant="ghost"
                    size="sm"
                    @click="
                        $emit('comment', {
                            field: 'section',
                            sectionIndex: index,
                        })
                    "
                >
                    <MessageSquarePlus /> Komentari
                </Button>
            </div>
            <p class="text-sm leading-relaxed whitespace-pre-wrap">
                {{ section.content }}
            </p>
            <div v-if="section.table" class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr>
                            <th
                                v-for="(column, c) in section.table.columns"
                                :key="c"
                                class="border bg-muted px-2 py-1 text-left font-medium"
                            >
                                {{ column }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, r) in section.table.rows" :key="r">
                            <td
                                v-for="(cell, c) in row"
                                :key="c"
                                class="border px-2 py-1 align-top"
                            >
                                {{ cell }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
