<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
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
import type { ManagedUser, Option, TenantSummary } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

const { user = null, tenantOptions = null } = defineProps<{
    action: RouteFormDefinition<'post'>;
    roleOptions: Option[];
    tenantOptions?: TenantSummary[] | null;
    user?: ManagedUser | null;
    submitLabel: string;
}>();
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
            <Select name="role" :default-value="user?.role.value" required>
                <SelectTrigger id="role" class="w-full">
                    <SelectValue placeholder="Pilih peran" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="option in roleOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="errors.role" />
        </div>

        <div v-if="tenantOptions" class="grid gap-2">
            <Label for="tenant_id">Perusahaan</Label>
            <Select name="tenant_id" required>
                <SelectTrigger id="tenant_id" class="w-full">
                    <SelectValue placeholder="Pilih perusahaan" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="tenant in tenantOptions"
                        :key="tenant.id"
                        :value="String(tenant.id)"
                    >
                        {{ tenant.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
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
