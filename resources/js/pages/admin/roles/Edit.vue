<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import RoleDeleteDialog from '@/components/admin/roles/RoleDeleteDialog.vue';
import RoleForm from '@/components/admin/roles/RoleForm.vue';
import RoleKindBadge from '@/components/admin/roles/RoleKindBadge.vue';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import type { Permission, PermissionGroup, RoleRecord } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Hak Akses', href: RoleController.index() }],
    },
});

defineProps<{
    role: RoleRecord;
    permissionGroups: PermissionGroup[];
    maxLevel: number;
    lockedPermissions: Permission[];
}>();
</script>

<template>
    <Head :title="role.name" />

    <div class="flex flex-col gap-8 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="role.name"
                :description="
                    role.is_system
                        ? 'Peran sistem: berlaku untuk semua perusahaan.'
                        : (role.tenant?.name ?? 'Peran perusahaan')
                "
            />
            <RoleKindBadge :is-system="role.is_system" />
        </div>

        <Alert v-if="!role.can.update">
            <AlertDescription>
                Anda hanya dapat melihat peran ini.
            </AlertDescription>
        </Alert>
        <Alert v-else-if="role.is_system">
            <AlertDescription>
                Perubahan izin peran sistem berlaku untuk semua perusahaan. Nama
                dan level peran sistem tidak dapat diubah.
            </AlertDescription>
        </Alert>

        <RoleForm
            :action="RoleController.update.form(role.id)"
            :groups="permissionGroups"
            :role="role"
            :max-level="maxLevel"
            :locked="lockedPermissions"
            :readonly="!role.can.update"
            submit-label="Simpan perubahan"
        />

        <RoleDeleteDialog v-if="role.can.delete" :role="role" />
    </div>
</template>
