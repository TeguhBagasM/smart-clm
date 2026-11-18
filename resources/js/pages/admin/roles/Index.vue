<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { Option, Permission } from '@/types';

type RoleRow = Option & { is_legacy: boolean; permissions: Permission[] };
type PermissionGroup = { group: string; permissions: Option<Permission>[] };

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Hak Akses', href: RoleController() }],
    },
});

defineProps<{
    roles: RoleRow[];
    permissionGroups: PermissionGroup[];
}>();
</script>

<template>
    <Head title="Hak Akses" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Hak Akses"
            description="Izin bawaan setiap peran. Super Admin selalu memiliki semua izin. Matriks ini belum dapat diubah."
        />

        <div class="rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow class="hover:bg-transparent">
                        <TableHead
                            class="sticky left-0 z-10 min-w-72 bg-background"
                        >
                            Izin
                        </TableHead>
                        <TableHead
                            v-for="role in roles"
                            :key="role.value"
                            class="text-center"
                        >
                            <span class="block">{{ role.label }}</span>
                            <Badge
                                v-if="role.is_legacy"
                                variant="outline"
                                class="mt-1 font-normal"
                            >
                                lama
                            </Badge>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template
                        v-for="group in permissionGroups"
                        :key="group.group"
                    >
                        <TableRow class="bg-muted/50 hover:bg-muted/50">
                            <TableCell
                                :colspan="roles.length + 1"
                                class="sticky left-0 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                {{ group.group }}
                            </TableCell>
                        </TableRow>
                        <TableRow
                            v-for="permission in group.permissions"
                            :key="permission.value"
                        >
                            <TableCell class="sticky left-0 z-10 bg-background">
                                <div class="font-medium">
                                    {{ permission.label }}
                                </div>
                                <code class="text-xs text-muted-foreground">
                                    {{ permission.value }}
                                </code>
                            </TableCell>
                            <TableCell
                                v-for="role in roles"
                                :key="role.value"
                                class="text-center"
                            >
                                <Check
                                    v-if="
                                        role.permissions.includes(
                                            permission.value,
                                        )
                                    "
                                    class="mx-auto size-4 text-primary"
                                    :aria-label="`${role.label}: diizinkan`"
                                />
                                <span v-else class="text-muted-foreground/40"
                                    >–</span
                                >
                            </TableCell>
                        </TableRow>
                    </template>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
