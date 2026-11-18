<script setup lang="ts">
import ClauseConfigController from '@/actions/App/Http/Controllers/Documents/Config/ClauseConfigController';
import DepartmentConfigController from '@/actions/App/Http/Controllers/Documents/Config/DepartmentConfigController';
import DocTypeConfigController from '@/actions/App/Http/Controllers/Documents/Config/DocTypeConfigController';
import NumberingConfigController from '@/actions/App/Http/Controllers/Documents/Config/NumberingConfigController';
import PageTabs from '@/components/PageTabs.vue';

defineProps<{ active: 'types' | 'numbering' | 'clauses' | 'departments' }>();

const tabs = [
    {
        key: 'types',
        label: 'Jenis dokumen',
        href: DocTypeConfigController.index(),
    },
    {
        key: 'numbering',
        label: 'Penomoran',
        href: NumberingConfigController.index(),
    },
    { key: 'clauses', label: 'Klausul', href: ClauseConfigController.index() },
    {
        key: 'departments',
        label: 'Departemen',
        href: DepartmentConfigController.index(),
    },
];
</script>

<template>
    <PageTabs label="Pengaturan dokumen" :tabs="tabs" :active="active" />
</template>
