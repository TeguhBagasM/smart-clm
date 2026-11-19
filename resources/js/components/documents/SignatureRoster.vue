<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import ReassignSignerDialog from '@/components/documents/actions/ReassignSignerDialog.vue';
import Panel from '@/components/documents/Panel.vue';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { RecipientOption, RosterEntry, VersionStatus } from '@/types';

/**
 * Who has to sign, in order, with a sentence on top saying where the document
 * stands and what happens next, so nobody has to read the steps to find out.
 */
const props = defineProps<{
    roster: RosterEntry[];
    status: VersionStatus;
    canSign: boolean;
    documentId: string;
    versionId: string;
    canReassign: boolean;
    options?: RecipientOption[];
}>();

const signed = computed(
    () => props.roster.filter((e) => e.status === 'approved').length,
);
const current = computed(() => props.roster.find((e) => e.is_current));
const whom = (entry: RosterEntry): string =>
    entry.assigned_to ?? entry.role ?? 'siapa pun yang berwenang';

const summary = computed(() => {
    if (props.status === 'draft')
        return 'Dokumen masih draft. Tanda tangan dimulai setelah dokumen diajukan.';
    if (props.status === 'under_review' && current.value) {
        return props.canSign
            ? 'Sekarang giliran Anda untuk menandatangani.'
            : `Menunggu tanda tangan ${whom(current.value)} (${signed.value + 1} dari ${props.roster.length}).`;
    }

    return signed.value === props.roster.length
        ? 'Semua tanda tangan sudah lengkap.'
        : '';
});

function stateOf(entry: RosterEntry): {
    text: string;
    tone: 'done' | 'now' | 'next';
} {
    if (entry.status === 'approved')
        return { text: 'Sudah menandatangani', tone: 'done' };
    if (entry.is_current)
        return {
            text: props.canSign ? 'Giliran Anda' : 'Sedang ditunggu',
            tone: 'now',
        };

    return {
        text: props.status === 'draft' ? 'Belum diajukan' : 'Menunggu giliran',
        tone: 'next',
    };
}
</script>

<template>
    <Panel title="Siapa yang menandatangani" :count="roster.length">
        <p
            v-if="summary"
            class="mb-4 rounded-md bg-muted px-3 py-2 text-sm"
            role="status"
        >
            {{ summary }}
        </p>
        <ol class="flex flex-col">
            <li
                v-for="(entry, index) in roster"
                :key="entry.order"
                class="relative flex gap-3 pb-5 last:pb-0"
            >
                <span
                    v-if="index < roster.length - 1"
                    aria-hidden="true"
                    :class="
                        cn(
                            'absolute top-8 bottom-0 left-3.5 w-px',
                            stateOf(entry).tone === 'done'
                                ? 'bg-success'
                                : 'bg-border',
                        )
                    "
                />
                <span
                    :class="
                        cn(
                            'relative flex size-7 shrink-0 items-center justify-center rounded-full border-2 text-xs font-semibold',
                            stateOf(entry).tone === 'done' &&
                                'border-success bg-success text-success-foreground',
                            stateOf(entry).tone === 'now' &&
                                'border-primary bg-primary text-primary-foreground',
                            stateOf(entry).tone === 'next' &&
                                'border-input bg-background text-muted-foreground',
                        )
                    "
                >
                    <Check
                        v-if="stateOf(entry).tone === 'done'"
                        class="size-4"
                        aria-hidden="true"
                    />
                    <template v-else>{{ entry.order }}</template>
                </span>
                <div class="flex min-w-0 flex-1 flex-col gap-0.5 text-sm">
                    <span class="font-medium">{{ entry.label }}</span>
                    <span>
                        {{
                            entry.status === 'approved'
                                ? entry.signed_by
                                : whom(entry)
                        }}
                        <span
                            v-if="entry.role && entry.assigned_to"
                            class="text-muted-foreground"
                            >· {{ entry.role }}</span
                        >
                    </span>
                    <span
                        :class="
                            cn(
                                'text-xs',
                                stateOf(entry).tone === 'next'
                                    ? 'text-muted-foreground'
                                    : 'font-medium',
                            )
                        "
                    >
                        {{ stateOf(entry).text
                        }}<template v-if="entry.status === 'approved'">
                            · {{ formatDateTime(entry.signed_at) }}</template
                        >
                    </span>
                    <div
                        v-if="canReassign && entry.status !== 'approved'"
                        class="mt-1 -ml-2.5"
                    >
                        <ReassignSignerDialog
                            :document-id="documentId"
                            :version-id="versionId"
                            :order="entry.order"
                            :label="entry.label"
                            :options="options"
                        />
                    </div>
                </div>
            </li>
        </ol>
    </Panel>
</template>
