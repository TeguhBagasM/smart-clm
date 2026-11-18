<script setup lang="ts">
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
                    <Select
                        :model-value="signer.assigned ?? NONE"
                        @update:model-value="assign(signer, $event)"
                    >
                        <SelectTrigger
                            :aria-label="`Penandatangan ${signer.label}`"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="NONE"
                                >Siapa pun dengan peran ini</SelectItem
                            >
                            <SelectItem
                                v-for="user in options.users"
                                :key="user.value"
                                :value="user.value"
                            >
                                {{ user.label
                                }}<template v-if="user.group">
                                    · {{ user.group }}</template
                                >
                            </SelectItem>
                        </SelectContent>
                    </Select>
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
                    <Select
                        :model-value="reviewer.role || NONE"
                        @update:model-value="
                            reviewer.role =
                                $event === NONE ? '' : String($event);
                            touch('signers');
                        "
                    >
                        <SelectTrigger
                            :aria-label="`Peran reviewer ${index + 1}`"
                            ><SelectValue placeholder="Peran"
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="NONE">Tanpa peran</SelectItem>
                            <SelectItem
                                v-for="role in options.roles"
                                :key="role.value"
                                :value="role.value"
                                >{{ role.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <Select
                        :model-value="reviewer.assigned ?? NONE"
                        @update:model-value="assign(reviewer, $event)"
                    >
                        <SelectTrigger
                            :aria-label="`Orang reviewer ${index + 1}`"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="NONE"
                                >Siapa pun dengan peran ini</SelectItem
                            >
                            <SelectItem
                                v-for="user in options.users"
                                :key="user.value"
                                :value="user.value"
                                >{{ user.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
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
