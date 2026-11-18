<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import UserForm from '@/components/admin/users/UserForm.vue';
import Heading from '@/components/Heading.vue';
import type { RoleOption, TenantSummary } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Pengguna', href: UserController.index() },
            { title: 'Tambah', href: UserController.create() },
        ],
    },
});

defineProps<{
    roleOptions: RoleOption[];
    tenantOptions: TenantSummary[] | null;
}>();
</script>

<template>
    <Head title="Tambah pengguna" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Tambah pengguna"
            description="Pengguna menerima email untuk mengatur kata sandinya sendiri."
        />

        <UserForm
            :action="UserController.store.form()"
            :role-options="roleOptions"
            :tenant-options="tenantOptions"
            submit-label="Buat pengguna"
        />
    </div>
</template>
