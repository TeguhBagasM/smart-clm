<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import DocumentTimelineController from '@/actions/App/Http/Controllers/Documents/DocumentTimelineController';
import Panel from '@/components/documents/Panel.vue';
import { Button } from '@/components/ui/button';
import { eventSentence } from '@/lib/eventText';
import { formatDateTime, versionLabel } from '@/lib/format';
import type { EventRow } from '@/types';

defineProps<{ documentId: string; events: EventRow[] }>();
</script>

<template>
    <Panel title="Yang sudah terjadi">
        <template #action>
            <Button variant="link" size="sm" as-child>
                <Link
                    :href="
                        DocumentTimelineController.index({
                            document: documentId,
                        })
                    "
                >
                    Lihat semua
                </Link>
            </Button>
        </template>
        <p v-if="events.length === 0" class="text-sm text-muted-foreground">
            Belum ada riwayat.
        </p>
        <ol v-else class="flex flex-col gap-3 text-sm">
            <li v-for="event in events" :key="event.id" class="flex flex-col">
                <span class="font-medium">
                    {{ eventSentence(event) }}
                    <span
                        v-if="event.version"
                        class="font-normal text-muted-foreground"
                    >
                        · {{ versionLabel(event.version) }}
                    </span>
                </span>
                <span v-if="event.summary" class="text-muted-foreground">
                    {{ event.summary }}
                </span>
                <span class="text-xs text-muted-foreground">
                    {{ formatDateTime(event.occurred_at) }}
                </span>
            </li>
        </ol>
    </Panel>
</template>
