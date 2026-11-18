<script setup lang="ts">
import { Check, Circle, Clock } from '@lucide/vue';
import Panel from '@/components/documents/Panel.vue';
import { formatDateTime } from '@/lib/format';
import type { RosterEntry } from '@/types';

defineProps<{ roster: RosterEntry[] }>();
</script>

<template>
    <Panel title="Lembar pengesahan" :count="roster.length">
        <ol class="flex flex-col gap-3">
            <li
                v-for="entry in roster"
                :key="entry.order"
                class="flex items-start gap-3 text-sm"
            >
                <Check
                    v-if="entry.status === 'approved'"
                    class="mt-0.5 size-4 shrink-0 text-success"
                    aria-label="Sudah ditandatangani"
                />
                <Clock
                    v-else-if="entry.is_current"
                    class="mt-0.5 size-4 shrink-0 text-warning"
                    aria-label="Sedang menunggu"
                />
                <Circle
                    v-else
                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                    aria-label="Belum giliran"
                />
                <div class="flex flex-col">
                    <span class="font-medium">{{ entry.label }}</span>
                    <span v-if="entry.status === 'approved'">
                        {{ entry.signed_by }}
                        <span class="text-muted-foreground">
                            · {{ formatDateTime(entry.signed_at) }}
                        </span>
                    </span>
                    <span v-else class="text-muted-foreground">
                        {{ entry.is_current ? 'Menunggu ' : 'Belum giliran: '
                        }}{{ entry.assigned_to ?? entry.role ?? 'siapa pun' }}
                    </span>
                </div>
            </li>
        </ol>
    </Panel>
</template>
