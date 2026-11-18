<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import UserStatusBadge from '@/components/admin/users/UserStatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { ManagedUser } from '@/types';

defineProps<{
    users: ManagedUser[];
    showTenant: boolean;
}>();

const lastLoginFormatter = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

function formatLastLogin(value: string | null): string {
    return value ? lastLoginFormatter.format(new Date(value)) : 'Belum pernah';
}
</script>

<template>
    <div class="rounded-xl border">
        <Table>
            <TableHeader>
                <TableRow class="hover:bg-transparent">
                    <TableHead>Pengguna</TableHead>
                    <TableHead>Peran</TableHead>
                    <TableHead v-if="showTenant">Perusahaan</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Login terakhir</TableHead>
                    <TableHead class="w-12"
                        ><span class="sr-only">Aksi</span></TableHead
                    >
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="user in users" :key="user.id">
                    <TableCell>
                        <div class="font-medium">{{ user.name }}</div>
                        <div class="text-muted-foreground">
                            {{ user.email }}
                        </div>
                    </TableCell>
                    <TableCell>
                        <Badge variant="outline">{{ user.role.label }}</Badge>
                    </TableCell>
                    <TableCell v-if="showTenant" class="text-muted-foreground">
                        {{ user.tenant?.name ?? 'Semua perusahaan' }}
                    </TableCell>
                    <TableCell>
                        <UserStatusBadge :is-active="user.is_active" />
                    </TableCell>
                    <TableCell class="text-muted-foreground">
                        {{ formatLastLogin(user.last_login_at) }}
                    </TableCell>
                    <TableCell class="text-right">
                        <Button
                            v-if="user.can.update"
                            variant="ghost"
                            size="icon-sm"
                            as-child
                        >
                            <Link
                                :href="UserController.edit(user.id)"
                                :aria-label="`Ubah ${user.name}`"
                            >
                                <Pencil />
                            </Link>
                        </Button>
                    </TableCell>
                </TableRow>
                <TableRow v-if="users.length === 0">
                    <TableCell
                        :colspan="showTenant ? 6 : 5"
                        class="py-10 text-center text-muted-foreground"
                    >
                        Belum ada pengguna.
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
