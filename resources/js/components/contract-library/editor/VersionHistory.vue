<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { LibraryVersionSummary } from '@/types';

/** Every version of the template, newest first. A published version never changes. */
defineProps<{
    versions: LibraryVersionSummary[];
    labels: Record<string, string>;
    publishedAt: (value: string) => string;
}>();
</script>

<template>
    <section class="flex flex-col gap-3" :aria-label="labels.title">
        <h3 class="text-sm font-semibold">{{ labels.title }}</h3>
        <p v-if="versions.length === 0" class="text-sm text-muted-foreground">
            {{ labels.empty }}
        </p>
        <ul class="flex flex-col gap-2">
            <li
                v-for="version in versions"
                :key="version.id"
                class="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3"
            >
                <span class="font-medium">Versi {{ version.version_no }}</span>
                <span class="flex flex-wrap items-center gap-2 text-sm">
                    <Badge
                        :variant="
                            version.status.value === 'published'
                                ? 'secondary'
                                : 'outline'
                        "
                        >{{ version.status.label }}</Badge
                    >
                    <span
                        v-if="version.published_at"
                        class="text-muted-foreground"
                        >{{ publishedAt(version.published_at) }}</span
                    >
                </span>
            </li>
        </ul>
    </section>
</template>
