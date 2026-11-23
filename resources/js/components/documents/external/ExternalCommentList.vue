<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { formatDateTime } from '@/lib/format';
import type { ExternalComment } from '@/types';

defineProps<{ comments: ExternalComment[] }>();

const fields: Record<string, string> = {
    purpose: 'Tujuan',
    scope: 'Ruang lingkup',
    section: 'Bagian isi',
    general: 'Umum',
};

function where(comment: ExternalComment): string {
    const { field, sectionHeading } = comment.anchor;

    return field === 'section' && sectionHeading
        ? `${fields.section}: ${sectionHeading}`
        : (fields[field ?? 'general'] ?? fields.general);
}
</script>

<template>
    <p v-if="comments.length === 0" class="text-sm text-muted-foreground">
        Belum ada komentar.
    </p>
    <ul v-else class="flex flex-col gap-3">
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
                <Badge v-if="!comment.external" variant="secondary">
                    Pemilik dokumen
                </Badge>
                <Badge v-if="comment.status === 'resolved'" variant="outline">
                    Sudah ditanggapi
                </Badge>
                <span class="text-xs text-muted-foreground">
                    {{ where(comment) }} ·
                    {{ formatDateTime(comment.created_at) }}
                </span>
            </div>
            <blockquote
                v-if="comment.anchor.quote"
                class="border-l-2 pl-2 text-muted-foreground"
            >
                {{ comment.anchor.quote }}
            </blockquote>
            <p class="whitespace-pre-wrap">{{ comment.body }}</p>
        </li>
    </ul>
</template>
