<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, History } from '@lucide/vue';
import { computed } from 'vue';
import DocumentController from '@/actions/App/Http/Controllers/Documents/DocumentController';
import DocumentTimelineController from '@/actions/App/Http/Controllers/Documents/DocumentTimelineController';
import VersionStatusBadge from '@/components/documents/VersionStatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { DocumentDetail } from '@/types';

const props = defineProps<{
    document: DocumentDetail;
    /** Link to the history page; hidden on the history page itself. */
    showHistoryLink?: boolean;
}>();

const latest = computed(() =>
    props.document.versions.find((version) => version.is_latest),
);
</script>

<template>
    <header class="flex flex-col gap-3">
        <Link
            :href="DocumentController.index()"
            class="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> Semua dokumen
        </Link>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex flex-col gap-1">
                <span class="font-mono text-sm text-muted-foreground">
                    {{ document.number }}
                </span>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ document.title }}
                </h1>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <slot />
                <Button v-if="showHistoryLink" variant="outline" as-child>
                    <Link
                        :href="
                            DocumentTimelineController.index({
                                document: document.id,
                            })
                        "
                    >
                        <History /> Riwayat lengkap
                    </Link>
                </Button>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <VersionStatusBadge
                v-if="latest"
                :status="latest.status"
                :label="latest.status_label"
            />
            <Badge variant="outline">{{ document.type.name }}</Badge>
            <Badge variant="outline">{{ document.department }}</Badge>
            <span class="text-muted-foreground">
                Pemilik: {{ document.owner }}
            </span>
            <span
                v-if="document.archive_category"
                class="text-muted-foreground"
            >
                · Arsip: {{ document.archive_category }}
            </span>
        </div>
    </header>
</template>
