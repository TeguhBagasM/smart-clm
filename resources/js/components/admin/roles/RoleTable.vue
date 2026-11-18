<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Eye, Pencil } from '@lucide/vue';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import RoleKindBadge from '@/components/admin/roles/RoleKindBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { RoleRecord } from '@/types';

defineProps<{
    roles: RoleRecord[];
    showTenant: boolean;
}>();
</script>

<template>
    <div class="rounded-xl border">
        <Table>
            <TableHeader>
                <TableRow class="hover:bg-transparent">
                    <TableHead>Peran</TableHead>
                    <TableHead>Jenis</TableHead>
                    <TableHead v-if="showTenant">Perusahaan</TableHead>
                    <TableHead class="text-right">Level</TableHead>
                    <TableHead class="text-right">Izin</TableHead>
                    <TableHead class="text-right">Pengguna</TableHead>
                    <TableHead class="w-12">
                        <span class="sr-only">Aksi</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="role in roles" :key="role.id">
                    <TableCell>
                        <div class="font-medium">{{ role.name }}</div>
                        <div
                            v-if="role.description"
                            class="max-w-xs truncate text-muted-foreground"
                        >
                            {{ role.description }}
                        </div>
                    </TableCell>
                    <TableCell>
                        <RoleKindBadge :is-system="role.is_system" />
                    </TableCell>
                    <TableCell v-if="showTenant" class="text-muted-foreground">
                        {{ role.tenant?.name ?? 'Semua perusahaan' }}
                    </TableCell>
                    <TableCell class="text-right tabular-nums">
                        {{ role.level }}
                    </TableCell>
                    <TableCell class="text-right tabular-nums">
                        {{ role.permissions.length }}
                    </TableCell>
                    <TableCell class="text-right tabular-nums">
                        {{ role.users_count }}
                    </TableCell>
                    <TableCell class="text-right">
                        <Button variant="ghost" size="icon-sm" as-child>
                            <Link
                                :href="RoleController.edit(role.id)"
                                :aria-label="`${role.can.update ? 'Ubah' : 'Lihat'} ${role.name}`"
                            >
                                <Pencil v-if="role.can.update" />
                                <Eye v-else />
                            </Link>
                        </Button>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
