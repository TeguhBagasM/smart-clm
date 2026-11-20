<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import EmployeeController from '@/actions/App/Http/Controllers/MasterData/EmployeeController';
import MasterDataIndexController from '@/actions/App/Http/Controllers/MasterData/MasterDataIndexController';
import ListPage from '@/components/list/ListPage.vue';
import EmployeeSheet from '@/components/master-data/EmployeeSheet.vue';
import EmployeeTable from '@/components/master-data/EmployeeTable.vue';
import MasterHeader from '@/components/master-data/MasterHeader.vue';
import { useMasterList } from '@/composables/useMasterList';
import type {
    EmployeeFormOptions,
    EmployeeRow,
    ListMeta,
    MasterCompany,
    MasterInfo,
    Paginated,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Daftar pilihan', href: MasterDataIndexController() },
        ],
    },
});

const props = defineProps<{
    items: Paginated<EmployeeRow>;
    list: ListMeta;
    info: MasterInfo;
    options: EmployeeFormOptions;
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

function toggle(row: EmployeeRow): void {
    const route = row.is_active
        ? EmployeeController.deactivate
        : EmployeeController.reactivate;

    void master.setActive(
        { id: row.id, name: row.name },
        row.is_active,
        () => route(row.id).url,
    );
}
</script>

<template>
    <Head :title="info.label" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <MasterHeader
            :title="info.label"
            :description="info.description"
            :company="company"
            :companies="companies"
        >
            <template #action>
                <EmployeeSheet
                    :info="info"
                    :options="options"
                    :company-id="company.id"
                />
            </template>
        </MasterHeader>
        <ListPage
            :list="list"
            :paginated="items"
            :only="['items', 'list']"
            :params="params"
            search-placeholder="Cari nama, nomor, atau jabatan…"
            empty-text="Belum ada karyawan. Tambahkan yang pertama dengan tombol di atas."
        >
            <EmployeeTable
                :rows="items.data"
                :list="list"
                :info="info"
                :options="options"
                :company-id="company.id"
                :busy="master.busy.value"
                @toggle="toggle"
            />
        </ListPage>
    </div>
</template>
