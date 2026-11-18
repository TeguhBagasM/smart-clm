<script setup lang="ts">
import { computed } from 'vue';
import Panel from '@/components/documents/Panel.vue';
import { Badge } from '@/components/ui/badge';
import { formatDateTime } from '@/lib/format';
import type { CommentAnchor, ReviewComment } from '@/types';

const props = defineProps<{ comments: ReviewComment[] }>();

const open = computed(
    () => props.comments.filter((comment) => comment.status === 'open').length,
);

const fields: Record<CommentAnchor['field'], string> = {
    purpose: 'Tujuan',
    scope: 'Ruang lingkup',
    section: 'Bagian isi',
    flow: 'Diagram alir',
    general: 'Umum',
};

function where(anchor: CommentAnchor): string {
    return anchor.field === 'section' && anchor.sectionHeading
        ? `${fields.section}: ${anchor.sectionHeading}`
        : fields[anchor.field];
}
</script>

<template>
    <Panel title="Komentar review" :count="comments.length">
        <p v-if="comments.length === 0" class="text-sm text-muted-foreground">
            Belum ada komentar pada versi ini.
        </p>
        <template v-else>
            <p class="mb-3 text-sm text-muted-foreground">
                {{ open }} terbuka, {{ comments.length - open }} selesai.
            </p>
            <ul class="flex flex-col gap-3">
                <li
                    v-for="comment in comments"
                    :key="comment.id"
                    :class="[
                        'flex flex-col gap-1 rounded-lg border p-3 text-sm',
                        comment.status === 'resolved' && 'opacity-70',
                    ]"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ comment.author }}</span>
                        <Badge v-if="comment.external" variant="outline">
                            Pihak eksternal
                        </Badge>
                        <Badge
                            :variant="
                                comment.status === 'open'
                                    ? 'secondary'
                                    : 'outline'
                            "
                        >
                            {{
                                comment.status === 'open'
                                    ? 'Terbuka'
                                    : 'Selesai'
                            }}
                        </Badge>
                        <span class="text-xs text-muted-foreground">
                            {{ where(comment.anchor) }} · putaran
                            {{ comment.round }} ·
                            {{ formatDateTime(comment.created_at) }}
                        </span>
                    </div>
                    <blockquote
                        v-if="comment.anchor.quote"
                        class="border-l-2 pl-2 text-xs text-muted-foreground italic"
                    >
                        {{ comment.anchor.quote }}
                    </blockquote>
                    <p class="whitespace-pre-line">{{ comment.body }}</p>
                    <p
                        v-if="comment.resolved_by"
                        class="text-xs text-muted-foreground"
                    >
                        Diselesaikan oleh {{ comment.resolved_by }}
                    </p>
                </li>
            </ul>
        </template>
    </Panel>
</template>
