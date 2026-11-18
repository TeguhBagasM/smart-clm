<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Send } from '@lucide/vue';
import { computed, ref } from 'vue';
import VersionDistributionController from '@/actions/App/Http/Controllers/Documents/VersionDistributionController';
import ActionDialog from '@/components/documents/actions/ActionDialog.vue';
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { RecipientOption } from '@/types';

defineProps<{
    documentId: string;
    versionId: string;
    options?: RecipientOption[];
}>();

const selected = ref<number[]>([]);
const mandatory = ref(true);
const term = ref('');

function load(): void {
    router.reload({ only: ['recipient_options'] });
}

function toggle(id: number, on: boolean): void {
    selected.value = on
        ? [...selected.value, id]
        : selected.value.filter((item) => item !== id);
}

const visible = computed(
    () => (people: RecipientOption[]) =>
        people.filter((person) =>
            `${person.name} ${person.role}`
                .toLowerCase()
                .includes(term.value.toLowerCase()),
        ),
);
</script>

<template>
    <ActionDialog
        :form="
            VersionDistributionController.store.form({
                document: documentId,
                version: versionId,
            })
        "
        :transform="
            () => ({ user_ids: selected, mandatory: mandatory ? 1 : 0 })
        "
        trigger-label="Distribusikan"
        :trigger-icon="Send"
        title="Distribusikan dokumen"
        description="Penerima akan tercatat harus membaca dokumen ini."
        :submit-label="`Kirim ke ${selected.length} penerima`"
        v-slot="{ errors }"
        @opened="load"
        @done="selected = []"
    >
        <Input
            v-model="term"
            type="search"
            placeholder="Cari nama atau peran"
        />
        <div
            class="flex max-h-56 flex-col gap-2 overflow-y-auto rounded-md border p-3"
        >
            <p v-if="!options" class="text-sm text-muted-foreground">
                Memuat daftar...
            </p>
            <Label
                v-for="person in visible(options ?? [])"
                :key="person.id"
                class="flex items-center gap-2 font-normal"
            >
                <Checkbox
                    :model-value="selected.includes(person.id)"
                    @update:model-value="toggle(person.id, $event === true)"
                />
                {{ person.name }}
                <span class="text-xs text-muted-foreground">
                    {{ person.role }}
                </span>
            </Label>
        </div>
        <InputError :message="errors.user_ids ?? errors['user_ids.0']" />
        <Label class="flex items-center gap-2 font-normal">
            <Checkbox
                :model-value="mandatory"
                @update:model-value="mandatory = $event === true"
            />
            Wajib dibaca dan dikonfirmasi
        </Label>
    </ActionDialog>
</template>
