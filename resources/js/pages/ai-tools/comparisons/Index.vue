<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import DocumentComparisonController from '@/actions/App/Http/Controllers/AiTools/DocumentComparisonController';
import ComparisonTable from '@/components/ai-tools/comparison/ComparisonTable.vue';
import Heading from '@/components/Heading.vue';
import ListPage from '@/components/list/ListPage.vue';
import { Button } from '@/components/ui/button';
import type { ComparisonListItem, ListMeta, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Bandingkan 2 Dokumen',
                href: DocumentComparisonController.create(),
            },
            {
                title: 'Riwayat',
                href: DocumentComparisonController.index(),
            },
        ],
    },
});

defineProps<{
    comparisons: Paginated<ComparisonListItem>;
    list: ListMeta;
}>();
</script>

<template>
    <Head title="Riwayat perbandingan" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-wrap items-start justify-between gap-3 [&>header]:mb-0"
        >
            <Heading
                title="Riwayat perbandingan"
                description="Perbandingan yang pernah Anda jalankan. Berkas dan hasilnya dihapus otomatis setelah masa simpan berakhir."
            />
            <Button as-child class="w-full md:w-auto">
                <Link :href="DocumentComparisonController.create()">
                    <Plus /> Perbandingan baru
                </Link>
            </Button>
        </div>

        <ListPage
            :list="list"
            :paginated="comparisons"
            :only="['comparisons', 'list']"
            search-placeholder="Cari nama dokumen..."
            empty-text="Belum ada perbandingan."
        >
            <ComparisonTable :rows="comparisons.data" :list="list" />
        </ListPage>
    </div>
</template>
