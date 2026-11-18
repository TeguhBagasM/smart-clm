<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import UserTable from '@/components/admin/users/UserTable.vue';
import Heading from '@/components/Heading.vue';
import SimplePagination from '@/components/SimplePagination.vue';
import { Button } from '@/components/ui/button';
import type { ManagedUser, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pengguna', href: UserController.index() }],
    },
});

defineProps<{
    users: Paginated<ManagedUser>;
}>();

const page = usePage();
const isCrossTenant = computed(() => page.props.auth.user.tenant === null);
</script>

<template>
    <Head title="Pengguna" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Pengguna"
                description="Kelola akun dan peran pengguna di perusahaan Anda."
            />
            <Button as-child>
                <Link :href="UserController.create()">
                    <Plus /> Tambah pengguna
                </Link>
            </Button>
        </div>

        <UserTable :users="users.data" :show-tenant="isCrossTenant" />

        <SimplePagination :links="users.links" :meta="users.meta" />
    </div>
</template>
