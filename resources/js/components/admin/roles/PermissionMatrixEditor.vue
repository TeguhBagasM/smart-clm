<script setup lang="ts">
import { computed } from 'vue';
import PermissionGroupCard from '@/components/admin/roles/PermissionGroupCard.vue';
import { usePermissions } from '@/composables/usePermissions';
import type { Permission, PermissionGroup } from '@/types';

const props = withDefaults(
    defineProps<{
        groups: PermissionGroup[];
        /** Permissions the role had when the page loaded; they stay removable. */
        initial?: Permission[];
        locked?: Permission[];
        disabled?: boolean;
    }>(),
    { initial: () => [], locked: () => [], disabled: false },
);

const selected = defineModel<Permission[]>({ required: true });

const { can } = usePermissions();

const selectedSet = computed(() => new Set(selected.value));
const lockedSet = computed(() => new Set(props.locked));

/** You can only grant what you hold yourself; removing is always allowed. */
const editable = computed(() => {
    if (props.disabled) {
        return new Set<Permission>();
    }

    return new Set<Permission>(
        props.groups
            .flatMap((group) => group.permissions)
            .map((permission) => permission.value)
            .filter((value) => can(value) || props.initial.includes(value)),
    );
});

function toggle(permission: Permission, checked: boolean): void {
    const next = new Set(selected.value);

    if (checked) {
        next.add(permission);
    } else {
        next.delete(permission);
    }

    selected.value = [...next];
}
</script>

<template>
    <div class="grid gap-4">
        <PermissionGroupCard
            v-for="group in groups"
            :key="group.group"
            :group="group.group"
            :permissions="group.permissions"
            :selected="selectedSet"
            :editable="editable"
            :locked="lockedSet"
            @toggle="toggle"
        />
    </div>
</template>
