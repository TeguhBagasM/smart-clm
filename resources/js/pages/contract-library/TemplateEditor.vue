<script setup lang="ts">
import { Head, router, Link } from '@inertiajs/vue3';
import { ArrowLeft, Copy, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import TemplateController from '@/actions/App/Http/Controllers/Templates/TemplateController';
import TemplateDraftController from '@/actions/App/Http/Controllers/Templates/TemplateDraftController';
import DraftForm from '@/components/contract-library/editor/DraftForm.vue';
import PreviewPanel from '@/components/contract-library/editor/PreviewPanel.vue';
import EditorHeader from '@/components/contract-library/editor/EditorHeader.vue';
import VersionHistory from '@/components/contract-library/editor/VersionHistory.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import StatusBadge from '@/components/master-data/StatusBadge.vue';
import { useMasterList } from '@/composables/useMasterList';
import { formatDate } from '@/lib/contractLibrary';
import type {
    LibraryClauseOption,
    LibraryFieldOption,
    LibraryPageInfo,
    LibraryTemplateRow,
    LibraryVersion,
    LibraryVersionSummary,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Templat kontrak', href: TemplateController.index() },
        ],
    },
});

const props = defineProps<{
    template: LibraryTemplateRow;
    version: LibraryVersion | null;
    versions: LibraryVersionSummary[];
    options: {
        clauses: LibraryClauseOption[];
        fields: LibraryFieldOption[];
        categories: { value: string; label: string }[];
    };
    info: LibraryPageInfo;
    company: { id: number; name: string };
}>();

const texts = computed(() => props.info.editor!);
const master = useMasterList(() => props.info);

/** The tab is kept in the address, so a reload or a shared link opens the same part. */
const tabs = ['draft', 'preview', 'history'] as const;
type Tab = (typeof tabs)[number];
const initial = new URLSearchParams(window.location.search).get('tab');
const tab = ref<Tab>(
    (tabs as readonly string[]).includes(initial ?? '')
        ? (initial as Tab)
        : 'draft',
);

function openTab(next: Tab): void {
    tab.value = next;
    const url = new URL(window.location.href);
    url.searchParams.set('tab', next);
    window.history.replaceState(window.history.state, '', url.toString());
}

const editable = computed(
    () =>
        props.template.draft !== null &&
        props.version?.status.value === 'draft',
);

function toggle(): void {
    const route = props.template.is_active
        ? TemplateController.deactivate
        : TemplateController.reactivate;

    void master.setActive(
        { id: props.template.id, name: props.template.name },
        props.template.is_active,
        () => route(props.template.id).url,
    );
}

function startDraft(): void {
    router.post(TemplateController.startDraft(props.template.id).url);
}

function copy(): void {
    router.post(TemplateController.duplicate(props.template.id).url);
}
</script>

<template>
    <Head :title="template.name" />
    <div class="flex flex-col gap-6 p-4 md:p-6">
        <EditorHeader
            :template="template"
            :texts="texts"
            :info="info"
            :categories="options.categories"
            :busy="master.busy.value === template.id"
            @start="startDraft"
            @copy="copy"
            @toggle="toggle"
        />

        <div
            role="tablist"
            :aria-label="texts.tabs_label"
            class="flex gap-1 overflow-x-auto border-b"
        >
            <button
                v-for="item in tabs"
                :key="item"
                type="button"
                role="tab"
                :aria-selected="tab === item"
                class="min-h-11 shrink-0 border-b-2 px-4 text-sm font-medium"
                :class="
                    tab === item
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="openTab(item)"
            >
                {{ texts.tabs[item] }}
            </button>
        </div>

        <section v-if="tab === 'draft'" role="tabpanel">
            <DraftForm
                v-if="editable && version"
                :version="version"
                :template-id="template.id"
                :clauses="options.clauses"
                :fields="options.fields"
                :info="info"
                :texts="texts"
            />
            <div v-else class="flex flex-col gap-3">
                <Alert class="text-sm"
                    ><AlertDescription>{{
                        texts.published_readonly
                    }}</AlertDescription></Alert
                >
                <p v-if="version" class="text-sm text-muted-foreground">
                    Versi {{ version.version_no }} ·
                    {{ version.clauses.length }} pasal ·
                    {{ version.parties.length }} pihak
                </p>
            </div>
        </section>
        <section v-else-if="tab === 'preview'" role="tabpanel">
            <PreviewPanel
                v-if="version"
                :template-id="template.id"
                :labels="{
                    title: texts.tabs.preview,
                    note: texts.preview_note,
                    refresh: texts.preview_refresh,
                    empty: texts.preview_empty,
                }"
                :fail-text="texts.save_failed"
            />
            <p v-else class="text-sm text-muted-foreground">
                {{ texts.preview_empty }}
            </p>
        </section>
        <section v-else role="tabpanel">
            <VersionHistory
                :versions="versions"
                :labels="{
                    title: texts.history_title,
                    empty: texts.history_empty,
                }"
                :published-at="
                    (value: string) =>
                        texts.history_published.replace(
                            ':date',
                            formatDate(value),
                        )
                "
            />
        </section>
    </div>
</template>
