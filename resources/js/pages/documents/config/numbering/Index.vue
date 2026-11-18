<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import NumberingConfigController from '@/actions/App/Http/Controllers/Documents/Config/NumberingConfigController';
import ConfigHeader from '@/components/documents/config/ConfigHeader.vue';
import MaskDialog from '@/components/documents/config/numbering/MaskDialog.vue';
import NumberingTable from '@/components/documents/config/numbering/NumberingTable.vue';
import ListPage from '@/components/list/ListPage.vue';
import type { ListMeta, NumberingRow, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Pengaturan dokumen',
                href: NumberingConfigController.index(),
            },
        ],
    },
});

defineProps<{
    types: Paginated<NumberingRow>;
    list: ListMeta;
    default_rule: { mask: string; example: string };
    tokens: string[];
}>();
</script>

<template>
    <Head title="Penomoran" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <ConfigHeader
            active="numbering"
            title="Pengaturan dokumen"
            description="Format nomor dokumen. Setiap jenis bisa punya format sendiri, atau mengikuti default."
        />

        <section
            class="flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex flex-col gap-1">
                <h2 class="text-base font-semibold">
                    Format default perusahaan
                </h2>
                <code class="text-sm">{{ default_rule.mask }}</code>
                <p class="text-sm text-muted-foreground">
                    Contoh:
                    <span class="font-mono">{{ default_rule.example }}</span>
                </p>
            </div>
            <MaskDialog :mask="default_rule.mask" :tokens="tokens" />
        </section>

        <ListPage
            :list="list"
            :paginated="types"
            :only="['types', 'list']"
            search-placeholder="Cari kode atau nama jenis dokumen..."
            empty-text="Belum ada jenis dokumen."
        >
            <NumberingTable :rows="types.data" :list="list" :tokens="tokens" />
        </ListPage>
    </div>
</template>
