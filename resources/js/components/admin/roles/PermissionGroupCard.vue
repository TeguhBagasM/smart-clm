<script setup lang="ts">
import { computed } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { Option, Permission } from '@/types';

const props = defineProps<{
    group: string;
    permissions: Option<Permission>[];
    selected: Set<Permission>;
    /** Permissions the user may switch on or off. */
    editable: Set<Permission>;
    /** Permissions that must stay on. */
    locked: Set<Permission>;
}>();

const emit = defineEmits<{
    toggle: [permission: Permission, checked: boolean];
}>();

const toggleable = computed(() =>
    props.permissions.filter(
        (permission) =>
            props.editable.has(permission.value) &&
            !props.locked.has(permission.value),
    ),
);

const allSelected = computed(
    () =>
        toggleable.value.length > 0 &&
        toggleable.value.every((permission) =>
            props.selected.has(permission.value),
        ),
);

function toggleAll(checked: boolean): void {
    toggleable.value.forEach((permission) =>
        emit('toggle', permission.value, checked),
    );
}
</script>

<template>
    <section class="rounded-xl border">
        <header
            class="flex items-center justify-between gap-4 border-b bg-muted/40 px-4 py-3"
        >
            <h3 class="text-sm font-semibold">{{ group }}</h3>
            <Label
                v-if="toggleable.length > 1"
                class="gap-2 text-xs font-normal text-muted-foreground"
            >
                <Checkbox
                    :model-value="allSelected"
                    :aria-label="`Pilih semua izin ${group}`"
                    @update:model-value="toggleAll(Boolean($event))"
                />
                Pilih semua
            </Label>
        </header>
        <ul class="divide-y">
            <li v-for="permission in permissions" :key="permission.value">
                <Label
                    class="items-start gap-3 px-4 py-3 font-normal"
                    :class="{
                        'cursor-not-allowed opacity-60':
                            !editable.has(permission.value) ||
                            locked.has(permission.value),
                    }"
                >
                    <Checkbox
                        class="mt-0.5"
                        :model-value="selected.has(permission.value)"
                        :disabled="
                            !editable.has(permission.value) ||
                            locked.has(permission.value)
                        "
                        @update:model-value="
                            emit('toggle', permission.value, Boolean($event))
                        "
                    />
                    <span class="grid gap-0.5">
                        <span class="text-sm">{{ permission.label }}</span>
                        <code class="text-xs text-muted-foreground">
                            {{ permission.value }}
                        </code>
                    </span>
                </Label>
            </li>
        </ul>
    </section>
</template>
