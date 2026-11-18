<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Form, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import PermissionMatrixEditor from '@/components/admin/roles/PermissionMatrixEditor.vue';
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
import { usePermissions } from '@/composables/usePermissions';
import type {
    Permission,
    PermissionGroup,
    RoleRecord,
    RoleTemplate,
    TenantSummary,
} from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

const props = withDefaults(
    defineProps<{
        action: RouteFormDefinition<'post'>;
        groups: PermissionGroup[];
        maxLevel: number;
        submitLabel: string;
        role?: RoleRecord | null;
        templates?: RoleTemplate[];
        tenantOptions?: TenantSummary[] | null;
        locked?: Permission[];
        readonly?: boolean;
    }>(),
    {
        role: null,
        templates: () => [],
        tenantOptions: null,
        locked: () => [],
        readonly: false,
    },
);

const { can } = usePermissions();

const permissions = ref<Permission[]>([...(props.role?.permissions ?? [])]);
const templateId = ref('');
const isSystem = props.role?.is_system ?? false;

watch(templateId, (id) => {
    const template = props.templates.find((item) => String(item.id) === id);

    if (template) {
        permissions.value = template.permissions.filter((key) => can(key));
    }
});

function transform(
    data: Record<string, FormDataConvertible>,
): Record<string, FormDataConvertible> {
    return isSystem
        ? { permissions: permissions.value }
        : { ...data, permissions: permissions.value };
}
</script>

<template>
    <Form
        v-bind="action"
        :transform="transform"
        :options="{ preserveScroll: true }"
        v-slot="{ errors, processing }"
        class="grid max-w-3xl gap-8"
    >
        <div v-if="!isSystem" class="grid max-w-xl gap-6">
            <div class="grid gap-2">
                <Label for="name">Nama peran</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="role?.name"
                    :disabled="readonly"
                    required
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="description">Deskripsi</Label>
                <Input
                    id="description"
                    name="description"
                    :default-value="role?.description ?? ''"
                    :disabled="readonly"
                />
                <InputError :message="errors.description" />
            </div>

            <div class="grid gap-2">
                <Label for="level">Level (0–{{ maxLevel }})</Label>
                <Input
                    id="level"
                    type="number"
                    name="level"
                    min="0"
                    :max="maxLevel"
                    :default-value="role?.level ?? Math.min(10, maxLevel)"
                    :disabled="readonly"
                    required
                />
                <p class="text-xs text-muted-foreground">
                    Pengguna hanya dapat mengelola pengguna dengan level setara
                    atau di bawah levelnya sendiri.
                </p>
                <InputError :message="errors.level" />
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
        </div>

        <div class="grid gap-4">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="text-base font-semibold">Hak akses</h2>
                    <p class="text-sm text-muted-foreground">
                        Anda hanya dapat memberikan izin yang Anda miliki.
                    </p>
                </div>
                <div v-if="templates.length" class="grid w-64 gap-2">
                    <Label for="template">Isi dari peran</Label>
                    <Select v-model="templateId">
                        <SelectTrigger id="template" class="w-full">
                            <SelectValue placeholder="Pilih peran" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="template in templates"
                                :key="template.id"
                                :value="String(template.id)"
                            >
                                {{ template.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <PermissionMatrixEditor
                v-model="permissions"
                :groups="groups"
                :initial="role?.permissions ?? []"
                :locked="locked"
                :disabled="readonly"
            />
            <InputError :message="errors.permissions" />
        </div>

        <div v-if="!readonly" class="flex items-center gap-3">
            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />
                {{ submitLabel }}
            </Button>
            <Button variant="ghost" as-child>
                <Link :href="RoleController.index()">Batal</Link>
            </Button>
        </div>
    </Form>
</template>
