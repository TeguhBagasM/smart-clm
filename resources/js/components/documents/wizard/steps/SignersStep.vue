<script setup lang="ts">
import SearchSelect from '@/components/SearchSelect.vue';
import { Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useWizard } from '@/composables/useDraftWizard';
import { removeItem } from '@/lib/arrays';
import type { DraftSigner } from '@/types';

const { state, touch, options } = useWizard();
const NONE = '__none';

const people = computed(() => [
    { value: NONE, label: 'Siapa pun dengan peran ini' },
    ...options.users.map((u) => ({
        value: u.value,
        label: u.label,
        description: u.group ?? undefined,
    })),
]);
const roles = computed(() => [
    { value: NONE, label: 'Tanpa peran' },
    ...options.roles.map((r) => ({ value: r.value, label: r.label })),
]);
const groups = computed(() => [
    { title: 'Kolom tanda tangan', list: state.signers, extra: false },
]);

function assign(signer: DraftSigner, value: unknown): void {
    signer.assigned = value === NONE ? null : String(value);
    touch('signers');
}

function addReviewer(): void {
    state.extra_reviewers.push({
        label: '',
        role: '',
        role_name: null,
        assigned: null,
    });
    touch('signers');
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <section
            v-for="group in groups"
            :key="group.title"
            class="flex flex-col gap-3"
        >
            <h3 class="text-sm font-medium">{{ group.title }}</h3>
            <p
                v-if="group.list.length === 0"
                class="text-sm text-muted-foreground"
            >
                Jenis dokumen ini tidak punya kolom tanda tangan. Siapa pun yang
                berwenang dapat menandatangani; tambahkan reviewer bila perlu.
            </p>
            <ul class="grid gap-3 md:grid-cols-2">
                <li
                    v-for="signer in group.list"
                    :key="signer.label + signer.role"
                    class="flex flex-col gap-2 rounded-lg border p-3"
                >
                    <span class="text-sm font-medium">{{ signer.label }}</span>
                    <span
                        v-if="signer.role_name"
                        class="text-xs text-muted-foreground"
                        >{{ signer.role_name }}</span
                    >
                    <SearchSelect
                        :model-value="signer.assigned ?? NONE"
                        :options="people"
                        :label="`Penandatangan ${signer.label}`"
                        search-placeholder="Cari nama atau jabatan…"
                        @update:model-value="assign(signer, $event)"
                    />
                </li>
            </ul>
        </section>

        <section class="flex flex-col gap-3">
            <h3 class="text-sm font-medium">Reviewer tambahan</h3>
            <ul class="flex flex-col gap-3">
                <li
                    v-for="(reviewer, index) in state.extra_reviewers"
                    :key="index"
                    class="grid gap-2 rounded-lg border p-3 md:grid-cols-[1fr_1fr_1fr_auto]"
                >
                    <Input
                        v-model="reviewer.label"
                        placeholder="Jabatan, mis. Reviewer Hukum"
                        :aria-label="`Jabatan reviewer ${index + 1}`"
                        @update:model-value="touch('signers')"
                    />
                    <SearchSelect
                        :model-value="reviewer.role || NONE"
                        :options="roles"
                        :label="`Peran reviewer ${index + 1}`"
                        placeholder="Peran"
                        search-placeholder="Cari peran…"
                        @update:model-value="
                            reviewer.role =
                                $event === NONE ? '' : String($event);
                            touch('signers');
                        "
                    />
                    <SearchSelect
                        :model-value="reviewer.assigned ?? NONE"
                        :options="people"
                        :label="`Penandatangan reviewer ${index + 1}`"
                        search-placeholder="Cari nama atau jabatan…"
                        @update:model-value="assign(reviewer, $event)"
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Hapus reviewer"
                        @click="
                            state.extra_reviewers = removeItem(
                                state.extra_reviewers,
                                index,
                            );
                            touch('signers');
                        "
                    >
                        <Trash2 class="text-destructive" />
                    </Button>
                </li>
            </ul>
            <Button
                type="button"
                variant="outline"
                class="self-start"
                @click="addReviewer"
                ><Plus /> Tambah reviewer</Button
            >
        </section>
    </div>
</template>
