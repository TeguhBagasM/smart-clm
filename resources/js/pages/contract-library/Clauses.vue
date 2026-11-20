<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import ClauseController from '@/actions/App/Http/Controllers/Templates/ClauseController';
import TemplateController from '@/actions/App/Http/Controllers/Templates/TemplateController';
import ClauseSheet from '@/components/contract-library/clauses/ClauseSheet.vue';
import ClauseTable from '@/components/contract-library/clauses/ClauseTable.vue';
import ListPage from '@/components/list/ListPage.vue';
import MasterHeader from '@/components/master-data/MasterHeader.vue';
import { useMasterList } from '@/composables/useMasterList';
import type {
    LibraryClauseRow,
    LibraryPageInfo,
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
    items: Paginated<LibraryClauseRow>;
    list: ListMeta;
    info: LibraryPageInfo;
    options: {
        categories: { value: string; label: string }[];
        field_codes: string[];
    };
    company: MasterCompany;
    companies: { value: number; label: string }[] | null;
}>();

const master = useMasterList(() => props.info);
const params = computed(() => {
    const kept: Record<string, string> = {};

    if (props.companies) {
        kept.tenant_id = String(props.company.id);
    }

    return kept;
});

function toggle(row: LibraryClauseRow): void {
    const route = row.is_active
        ? ClauseController.deactivate
        : ClauseController.reactivate;

    void master.setActive(
        { id: row.id, name: row.title, usage_count: row.usage_count },
        row.is_active,
        () => route(row.id).url,
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
            <template #action>
                <ClauseSheet
                    :info="info"
                    :categories="options.categories"
                    :field-codes="options.field_codes"
                />
            </template>
        </MasterHeader>
        <ListPage
            :list="list"
            :paginated="items"
            :only="['items', 'list']"
            :params="params"
            :search-placeholder="info.search"
            :empty-text="info.empty"
        >
            <ClauseTable
                :rows="items.data"
                :list="list"
                :info="info"
                :categories="options.categories"
                :field-codes="options.field_codes"
                :busy="master.busy.value"
                @toggle="toggle"
            />
        </ListPage>
    </div>
</template>
