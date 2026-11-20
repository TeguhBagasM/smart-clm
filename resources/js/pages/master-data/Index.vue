<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import MasterDataIndexController from '@/actions/App/Http/Controllers/MasterData/MasterDataIndexController';
import IndexCards from '@/components/master-data/IndexCards.vue';
import MasterHeader from '@/components/master-data/MasterHeader.vue';
import type { MasterCompany, MasterGroup } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Daftar pilihan', href: MasterDataIndexController() },
        ],
    },
});

defineProps<{
    groups: MasterGroup[];
    company: MasterCompany | null;
    companies: { value: number; label: string }[] | null;
    needs_company: boolean;
    texts: { no_access: string; choose_company: string };
}>();
</script>

<template>
    <Head title="Daftar pilihan" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <MasterHeader
            title="Daftar pilihan"
            description="Pilihan yang muncul di formulir dokumen dan data karyawan. Atur di sini, supaya semua orang memilih dari daftar yang sama."
            :company="company"
            :companies="companies"
            :notice="needs_company ? texts.choose_company : null"
        />
        <IndexCards
            v-if="groups.length > 0"
            :groups="groups"
            :company-id="companies ? (company?.id ?? null) : null"
        />
        <p
            v-else
            class="rounded-xl border border-dashed p-8 text-center text-muted-foreground"
        >
            {{ texts.no_access }}
        </p>
    </div>
</template>
