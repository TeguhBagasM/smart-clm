<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import DocumentController from '@/actions/App/Http/Controllers/Documents/DocumentController';
import Panel from '@/components/documents/Panel.vue';
import VersionStatusBadge from '@/components/documents/VersionStatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { formatDate, versionLabel } from '@/lib/format';
import type { VersionRow } from '@/types';

defineProps<{
    documentId: string;
    versions: VersionRow[];
    selectedId: string | null;
}>();
</script>

<template>
    <Panel title="Versi" :count="versions.length">
        <ol class="flex flex-col gap-2">
            <li v-for="version in versions" :key="version.id">
                <Link
                    :href="
                        DocumentController.show(
                            { document: documentId },
                            { query: { version: version.id } },
                        )
                    "
                    :only="['version']"
                    preserve-scroll
                    preserve-state
                    :aria-current="
                        version.id === selectedId ? 'true' : undefined
                    "
                    :class="[
                        'flex flex-col gap-1.5 rounded-lg border p-3 text-sm transition-colors hover:bg-accent',
                        version.id === selectedId && 'border-primary bg-accent',
                    ]"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">
                            {{ versionLabel(version) }}
                        </span>
                        <Badge v-if="version.is_current">Berlaku</Badge>
                        <Badge v-else-if="version.is_latest" variant="outline">
                            Terbaru
                        </Badge>
                    </div>
                    <VersionStatusBadge
                        :status="version.status"
                        :label="version.status_label"
                    />
                    <span
                        v-if="version.effective_at"
                        class="text-xs text-muted-foreground"
                    >
                        Berlaku {{ formatDate(version.effective_at) }}
                        <template v-if="version.superseded_at">
                            – {{ formatDate(version.superseded_at) }}
                        </template>
                    </span>
                    <span
                        v-if="version.change_summary"
                        class="line-clamp-2 text-xs text-muted-foreground"
                    >
                        {{ version.change_summary }}
                    </span>
                </Link>
            </li>
        </ol>
    </Panel>
</template>
