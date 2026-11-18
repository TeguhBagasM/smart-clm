<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import UserForm from '@/components/admin/users/UserForm.vue';
import UserStatusToggle from '@/components/admin/users/UserStatusToggle.vue';
import Heading from '@/components/Heading.vue';
import type { ManagedUser, Option } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pengguna', href: UserController.index() }],
    },
});

defineProps<{
    user: { data: ManagedUser };
    roleOptions: Option[];
}>();
</script>

<template>
    <Head :title="`Ubah ${user.data.name}`" />

    <div class="flex flex-col gap-8 p-4 md:p-6">
        <Heading
            :title="user.data.name"
            :description="
                user.data.tenant ? user.data.tenant.name : 'Semua perusahaan'
            "
        />

        <UserForm
            :action="UserController.update.form(user.data.id)"
            :user="user.data"
            :role-options="roleOptions"
            submit-label="Simpan perubahan"
        />

        <UserStatusToggle :user="user.data" />
    </div>
</template>
