<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, Copy, Plus } from '@lucide/vue';
import TemplateController from '@/actions/App/Http/Controllers/Templates/TemplateController';
import PublishDialog from '@/components/contract-library/editor/PublishDialog.vue';
import TemplateSheet from '@/components/contract-library/templates/TemplateSheet.vue';
import StatusBadge from '@/components/master-data/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type {
    LibraryEditorTexts,
    LibraryPageInfo,
    LibraryTemplateRow,
} from '@/types';

/**
 * The top of the editor: the template, its state, and the actions on it. The
 * page handles each action; the publish dialog posts its own form.
 */
defineProps<{
    template: LibraryTemplateRow;
    texts: LibraryEditorTexts;
    info: LibraryPageInfo;
    categories: { value: string; label: string }[];
    busy: boolean;
}>();

defineEmits<{ start: []; copy: []; toggle: [] }>();
</script>

<template>
    <div class="flex flex-col gap-4">
        <Link
            :href="TemplateController.index().url"
            class="inline-flex min-h-11 w-fit items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" aria-hidden="true" />{{ texts.back }}
        </Link>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 flex-col gap-2">
                <h1 class="text-2xl font-semibold break-words">
                    {{ template.name }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ texts.description }}
                </p>
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <Badge variant="outline">{{ template.module.label }}</Badge>
                    <Badge
                        v-if="template.published_version"
                        variant="secondary"
                    >
                        Versi terbit {{ template.published_version.version_no }}
                    </Badge>
                    <Badge v-else variant="outline">Belum terbit</Badge>
                    <Badge v-if="template.draft" variant="outline">
                        Draf {{ template.draft.version_no }}
                    </Badge>
                    <StatusBadge :active="template.is_active" />
                </div>
            </div>
            <div
                class="flex w-full flex-col gap-2 md:w-auto md:flex-row md:flex-wrap"
            >
                <TemplateSheet
                    v-if="template.can.update"
                    :info="info"
                    :categories="categories"
                    :row="template"
                />
                <PublishDialog
                    v-if="template.draft && template.can.publish"
                    :template-id="template.id"
                    :draft-revision="template.draft.draft_revision"
                    :version-no="template.draft.version_no"
                    :previous-version-no="
                        template.published_version?.version_no ?? null
                    "
                    :title="texts.publish_title"
                    :message="texts.publish_message"
                    :first-message="texts.publish_first"
                    :confirm-label="texts.publish"
                    :disabled="busy"
                />
                <Button
                    v-if="!template.draft && template.can.startDraft"
                    type="button"
                    variant="secondary"
                    class="h-11 w-full md:h-9 md:w-auto"
                    @click="$emit('start')"
                >
                    <Plus />{{ texts.start_draft }}
                </Button>
                <Button
                    v-if="template.can.duplicate"
                    type="button"
                    variant="outline"
                    class="h-11 w-full md:h-9 md:w-auto"
                    @click="$emit('copy')"
                >
                    <Copy />{{ texts.duplicate }}
                </Button>
                <Button
                    v-if="template.can.deactivate"
                    type="button"
                    variant="ghost"
                    class="h-11 w-full md:h-9 md:w-auto"
                    :disabled="busy"
                    @click="$emit('toggle')"
                >
                    {{ template.is_active ? 'Sembunyikan' : 'Tampilkan lagi' }}
                </Button>
            </div>
        </div>
    </div>
</template>
