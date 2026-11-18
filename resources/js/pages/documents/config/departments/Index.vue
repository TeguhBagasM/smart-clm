<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DepartmentConfigController from '@/actions/App/Http/Controllers/Documents/Config/DepartmentConfigController';
import ConfigHeader from '@/components/documents/config/ConfigHeader.vue';
import DepartmentDialog from '@/components/documents/config/departments/DepartmentDialog.vue';
import DepartmentTable from '@/components/documents/config/departments/DepartmentTable.vue';
import ListPage from '@/components/list/ListPage.vue';
import type { DepartmentRow, ListMeta, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Pengaturan dokumen',
                href: DepartmentConfigController.index(),
            },
        ],
    },
});

defineProps<{ departments: Paginated<DepartmentRow>; list: ListMeta }>();
</script>

<template>
    <Head title="Departemen" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <ConfigHeader
            active="departments"
            title="Pengaturan dokumen"
            description="Departemen menjadi bagian nomor dokumen dan folder tempat dokumen disimpan."
        >
            <template #action>
                <DepartmentDialog />
            </template>
        </ConfigHeader>

        <ListPage
            :list="list"
            :paginated="departments"
            :only="['departments', 'list']"
            search-placeholder="Cari kode atau nama departemen..."
            empty-text="Belum ada departemen. Departemen juga dibuat otomatis saat dokumen pertama dibuat."
        >
            <DepartmentTable :rows="departments.data" :list="list" />
        </ListPage>
    </div>
</template>
