<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import FieldController from '@/actions/App/Http/Controllers/Templates/VariableController';
import TemplateController from '@/actions/App/Http/Controllers/Templates/TemplateController';
import FieldSheet from '@/components/contract-library/fields/FieldSheet.vue';
import FieldTable from '@/components/contract-library/fields/FieldTable.vue';
import ListPage from '@/components/list/ListPage.vue';
import MasterHeader from '@/components/master-data/MasterHeader.vue';
import { useMasterList } from '@/composables/useMasterList';
import type {
    LibraryFieldRow,
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
    items: Paginated<LibraryFieldRow>;
    list: ListMeta;
    info: LibraryPageInfo;
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

function toggle(row: LibraryFieldRow): void {
    const route = row.is_active
        ? FieldController.deactivate
        : FieldController.reactivate;

    void master.setActive(row, row.is_active, () => route(row.id).url);
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
            <template #action><FieldSheet :info="info" /></template>
        </MasterHeader>
        <ListPage
            :list="list"
            :paginated="items"
            :only="['items', 'list']"
            :params="params"
            :search-placeholder="info.search"
            :empty-text="info.empty"
        >
            <FieldTable
                :rows="items.data"
                :list="list"
                :info="info"
                :busy="master.busy.value"
                @toggle="toggle"
            />
        </ListPage>
    </div>
</template>
