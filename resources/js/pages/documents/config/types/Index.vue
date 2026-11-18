<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import DocTypeConfigController from '@/actions/App/Http/Controllers/Documents/Config/DocTypeConfigController';
import ConfigHeader from '@/components/documents/config/ConfigHeader.vue';
import DocTypeTable from '@/components/documents/config/types/DocTypeTable.vue';
import ListPage from '@/components/list/ListPage.vue';
import { Button } from '@/components/ui/button';
import type { DocTypeRow, ListMeta, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Pengaturan dokumen',
                href: DocTypeConfigController.index(),
            },
        ],
    },
});

defineProps<{ types: Paginated<DocTypeRow>; list: ListMeta }>();
</script>

<template>
    <Head title="Jenis dokumen" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <ConfigHeader
            active="types"
            title="Pengaturan dokumen"
            description="Atur jenis dokumen, format nomor, klausul, dan departemen perusahaan Anda."
        >
            <template #action>
                <Button as-child>
                    <Link :href="DocTypeConfigController.create()">
                        <Plus /> Tambah jenis dokumen
                    </Link>
                </Button>
            </template>
        </ConfigHeader>

        <ListPage
            :list="list"
            :paginated="types"
            :only="['types', 'list']"
            search-placeholder="Cari kode atau nama jenis dokumen..."
            empty-text="Belum ada jenis dokumen."
        >
            <DocTypeTable :rows="types.data" :list="list" />
        </ListPage>
    </div>
</template>
