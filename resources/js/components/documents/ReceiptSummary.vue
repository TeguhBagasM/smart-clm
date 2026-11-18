<script setup lang="ts">
import Panel from '@/components/documents/Panel.vue';
import { Badge } from '@/components/ui/badge';
import { formatDateTime } from '@/lib/format';
import type { ReceiptSummary } from '@/types';

defineProps<{ receipts: ReceiptSummary }>();

const labels = {
    pending: 'Belum dibuka',
    read: 'Sudah dibuka',
    acknowledged: 'Dikonfirmasi',
} as const;
</script>

<template>
    <Panel title="Tanda terima baca">
        <p
            v-if="receipts.recipients.length === 0"
            class="text-sm text-muted-foreground"
        >
            Versi ini belum didistribusikan.
        </p>
        <template v-else>
            <div class="mb-3 flex flex-wrap gap-2">
                <Badge
                    v-for="(label, status) in labels"
                    :key="status"
                    variant="secondary"
                >
                    {{ label }}: {{ receipts.counts[status] }}
                </Badge>
            </div>
            <ul class="flex flex-col gap-2 text-sm">
                <li
                    v-for="receipt in receipts.recipients"
                    :key="receipt.name"
                    class="flex flex-wrap justify-between gap-2"
                >
                    <span>{{ receipt.name }}</span>
                    <span class="text-muted-foreground">
                        {{ labels[receipt.status]
                        }}<template
                            v-if="receipt.acknowledged_at ?? receipt.read_at"
                        >
                            ·
                            {{
                                formatDateTime(
                                    receipt.acknowledged_at ?? receipt.read_at,
                                )
                            }}
                        </template>
                    </span>
                </li>
            </ul>
        </template>
    </Panel>
</template>
