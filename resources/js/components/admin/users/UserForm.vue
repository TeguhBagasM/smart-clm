<script setup lang="ts">
import SearchSelect from '@/components/SearchSelect.vue';
import { Form, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import type { ManagedUser, RoleOption, TenantSummary } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

const {
    user = null,
    tenantOptions = null,
    roleOptions,
} = defineProps<{
    action: RouteFormDefinition<'post'>;
    roleOptions: RoleOption[];
    tenantOptions?: TenantSummary[] | null;
    user?: ManagedUser | null;
    submitLabel: string;
}>();

const roleId = ref(user ? String(user.role.id) : '');
const tenantId = ref('');

/** A super admin picks the tenant first; only its custom roles are offered. */
const visibleRoleOptions = computed(() =>
    roleOptions.filter(
        (option) =>
            tenantOptions === null ||
            option.tenant_id === null ||
            String(option.tenant_id) === tenantId.value,
    ),
);

watch(tenantId, () => {
    if (
        !visibleRoleOptions.value.some(
            (option) => option.value === roleId.value,
        )
    ) {
        roleId.value = '';
    }
});
</script>

<template>
    <Form
        v-bind="action"
        :options="{ preserveScroll: true }"
        v-slot="{ errors, processing }"
        class="grid max-w-xl gap-6"
    >
        <div class="grid gap-2">
            <Label for="name">Nama lengkap</Label>
            <Input
                id="name"
                name="name"
                :default-value="user?.name"
                required
                autocomplete="off"
            />
            <InputError :message="errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="email">Email</Label>
            <Input
                id="email"
                type="email"
                name="email"
                :default-value="user?.email"
                required
                autocomplete="off"
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="role">Peran</Label>
            <SearchSelect
                id="role"
                v-model="roleId"
                name="role_id"
                :options="
                    visibleRoleOptions.map((o) => ({
                        value: String(o.value),
                        label: o.label,
                    }))
                "
                label="Peran"
                placeholder="Pilih peran"
                search-placeholder="Cari peran…"
            />
            <InputError :message="errors.role_id" />
        </div>

        <div v-if="tenantOptions" class="grid gap-2">
            <Label for="tenant_id">Perusahaan</Label>
            <SearchSelect
                id="tenant_id"
                v-model="tenantId"
                name="tenant_id"
                :options="
                    tenantOptions.map((t) => ({
                        value: String(t.id),
                        label: t.name,
                    }))
                "
                label="Perusahaan"
                placeholder="Pilih perusahaan"
                search-placeholder="Cari perusahaan…"
            />
            <InputError :message="errors.tenant_id" />
        </div>

        <div class="flex items-center gap-3">
            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />
                {{ submitLabel }}
            </Button>
            <Button variant="ghost" as-child>
                <Link :href="UserController.index()">Batal</Link>
            </Button>
        </div>
    </Form>
</template>
