<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import RoleForm from '@/components/admin/roles/RoleForm.vue';
import Heading from '@/components/Heading.vue';
import type { PermissionGroup, RoleTemplate, TenantSummary } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Hak Akses', href: RoleController.index() },
            { title: 'Tambah', href: RoleController.create() },
        ],
    },
});

defineProps<{
    permissionGroups: PermissionGroup[];
    templates: RoleTemplate[];
    maxLevel: number;
    tenantOptions: TenantSummary[] | null;
}>();
</script>

<template>
    <Head title="Tambah peran" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Tambah peran"
            description="Peran baru hanya berlaku di perusahaan Anda."
        />

        <RoleForm
            :action="RoleController.store.form()"
            :groups="permissionGroups"
            :templates="templates"
            :max-level="maxLevel"
            :tenant-options="tenantOptions"
            submit-label="Buat peran"
        />
    </div>
</template>
