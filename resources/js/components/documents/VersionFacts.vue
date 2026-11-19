<script setup lang="ts">
import Panel from '@/components/documents/Panel.vue';
import { Badge } from '@/components/ui/badge';
import { formatDate } from '@/lib/format';
import type { VersionDetail } from '@/types';

defineProps<{ version: VersionDetail }>();
</script>

<template>
    <Panel title="Keterangan versi">
        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
            <dt class="text-muted-foreground">Dibuat oleh</dt>
            <dd>{{ version.created_by }}</dd>
            <dt class="text-muted-foreground">Berlaku sejak</dt>
            <dd>{{ formatDate(version.effective_at) }}</dd>
            <template v-if="version.superseded_at">
                <dt class="text-muted-foreground">Tidak berlaku sejak</dt>
                <dd>{{ formatDate(version.superseded_at) }}</dd>
            </template>
            <template v-if="version.scheduled_effective_at">
                <dt class="text-muted-foreground">Rilis terjadwal</dt>
                <dd>{{ formatDate(version.scheduled_effective_at) }}</dd>
            </template>
            <dt class="text-muted-foreground">Perlu ditinjau ulang pada</dt>
            <dd>{{ formatDate(version.review_due_at) }}</dd>
            <dt class="text-muted-foreground">Tinjauan ke-</dt>
            <dd>{{ version.review_round }}</dd>
            <dt class="text-muted-foreground">Tinjauan pihak luar</dt>
            <dd>
                <template v-if="version.external_review">
                    <Badge variant="outline">
                        {{
                            version.external_review.locked
                                ? 'Terkunci (sudah disetujui)'
                                : 'Link aktif'
                        }}
                    </Badge>
                </template>
                <template v-else>Tidak dipakai</template>
            </dd>
        </dl>
        <p v-if="version.change_summary" class="mt-3 border-t pt-3 text-sm">
            <span class="text-muted-foreground">Ringkasan perubahan:</span>
            {{ version.change_summary }}
        </p>
    </Panel>
</template>
