<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import VendorController from '@/actions/App/Http/Controllers/MasterData/VendorController';
import ListPage from '@/components/list/ListPage.vue';
import MasterHeader from '@/components/master-data/MasterHeader.vue';
import VendorSheet from '@/components/master-data/VendorSheet.vue';
import VendorTable from '@/components/master-data/VendorTable.vue';
import { useMasterList } from '@/composables/useMasterList';
import type {
    ListMeta,
    MasterCompany,
    Paginated,
    VendorInfo,
    VendorRow,
} from '@/types';

const props = defineProps<{
    items: Paginated<VendorRow>;
    list: ListMeta;
    info: VendorInfo;
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

function toggle(row: VendorRow): void {
    const route = row.is_active
        ? VendorController.deactivate
        : VendorController.reactivate;

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
            :notice="info.usage_notice"
        >
            <template #action>
                <VendorSheet :info="info" :company-id="company.id" />
            </template>
        </MasterHeader>
        <ListPage
            :list="list"
            :paginated="items"
            :only="['items', 'list']"
            :params="params"
            :search-placeholder="info.search_placeholder"
            :empty-text="info.empty"
        >
            <VendorTable
                :rows="items.data"
                :list="list"
                :info="info"
                :company-id="company.id"
                :busy="master.busy.value"
                @toggle="toggle"
            />
        </ListPage>
    </div>
</template>
