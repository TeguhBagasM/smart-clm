<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TemplateController from '@/actions/App/Http/Controllers/Templates/TemplateController';
import ListPage from '@/components/list/ListPage.vue';
import MasterHeader from '@/components/master-data/MasterHeader.vue';
import TemplateSheet from '@/components/contract-library/templates/TemplateSheet.vue';
import TemplateTable from '@/components/contract-library/templates/TemplateTable.vue';
import { useMasterList } from '@/composables/useMasterList';
import type {
    LibraryPageInfo,
    LibraryTemplateRow,
    ListMeta,
    MasterCompany,
    Paginated,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Klausul & Template', href: TemplateController.index() },
        ],
    },
});

const props = defineProps<{
    items: Paginated<LibraryTemplateRow>;
    list: ListMeta;
    info: LibraryPageInfo;
    options: { categories: { value: string; label: string }[] };
    company: MasterCompany;
    companies: { value: number; label: string }[] | null;
}>();

const master = useMasterList(() => props.info);
const copying = ref<number | null>(null);
const params = computed(() => {
    const kept: Record<string, string> = {};

    if (props.companies) {
        kept.tenant_id = String(props.company.id);
    }

    return kept;
});

function toggle(row: LibraryTemplateRow): void {
    const route = row.is_active
        ? TemplateController.deactivate
        : TemplateController.reactivate;

    void master.setActive(
        { id: row.id, name: row.name },
        row.is_active,
        () => route(row.id).url,
    );
}

/** A copy opens at once in its own editor; the original is not touched. */
function copy(row: LibraryTemplateRow): void {
    copying.value = row.id;
    router.post(
        TemplateController.duplicate(row.id).url,
        {},
        { onFinish: () => (copying.value = null) },
    );
}
</script>

<template>
    <Head :title="info.title" />
    <div class="flex flex-col gap-5 p-4 md:p-6">
        <MasterHeader
            :title="info.title"
            :description="info.description"
            :company="company"
            :companies="companies"
        >
            <template #action
                ><TemplateSheet :info="info" :categories="options.categories"
            /></template>
        </MasterHeader>
        <ListPage
            :list="list"
            :paginated="items"
            :only="['items', 'list']"
            :params="params"
            :search-placeholder="info.search"
            :empty-text="info.empty"
        >
            <TemplateTable
                :rows="items.data"
                :list="list"
                :info="info"
                :categories="options.categories"
                :busy="master.busy.value ?? copying"
                @toggle="toggle"
                @copy="copy"
            />
        </ListPage>
    </div>
</template>
