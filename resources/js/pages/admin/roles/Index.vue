<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import RoleTable from '@/components/admin/roles/RoleTable.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import type { RoleRecord } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Hak Akses', href: RoleController.index() }],
    },
});

defineProps<{
    roles: RoleRecord[];
}>();

const page = usePage();
const { can } = usePermissions();
const isCrossTenant = computed(() => page.props.auth.user.tenant === null);
</script>

<template>
    <Head title="Hak Akses" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Hak Akses"
                description="Kelola peran dan izin yang dimiliki setiap peran. Peran sistem berlaku untuk semua perusahaan."
            />
            <Button v-if="can('roles.manage')" as-child>
                <Link :href="RoleController.create()">
                    <Plus /> Tambah peran
                </Link>
            </Button>
        </div>

        <RoleTable :roles="roles" :show-tenant="isCrossTenant" />
    </div>
</template>
