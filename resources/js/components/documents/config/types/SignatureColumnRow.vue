<script setup lang="ts">
import { ArrowDown, ArrowUp, Trash2 } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import SignerModeFields from '@/components/documents/config/types/SignerModeFields.vue';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ChoiceOption, SignatureColumn, SignerOption } from '@/types';

const ANYONE = '__anyone__';

defineProps<{
    column: SignatureColumn;
    index: number;
    last: boolean;
    roleOptions: ChoiceOption[];
    signerOptions: SignerOption[];
    first: boolean;
    error?: string;
}>();

defineEmits<{
    change: [patch: Partial<SignatureColumn>];
    move: [step: -1 | 1];
    remove: [];
}>();
</script>

<template>
    <div class="flex flex-col gap-2 rounded-md border p-3">
        <div class="grid gap-2 sm:grid-cols-[1fr_1fr_1fr_auto]">
            <Input
                :model-value="column.label"
                placeholder="Nama kolom, mis. Disetujui oleh"
                :aria-label="`Nama kolom ${index + 1}`"
                @update:model-value="$emit('change', { label: String($event) })"
            />
            <Select
                :model-value="column.role || ANYONE"
                @update:model-value="
                    $emit('change', {
                        role: $event === ANYONE ? '' : String($event),
                    })
                "
            >
                <SelectTrigger
                    class="w-full"
                    aria-label="Peran penandatangan (kandidat)"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="ANYONE">Siapa pun</SelectItem>
                    <SelectItem
                        v-for="role in roleOptions"
                        :key="role.value"
                        :value="role.value"
                    >
                        {{ role.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Input
                :model-value="column.roleName ?? ''"
                placeholder="Jabatan di lembar (opsional)"
                aria-label="Jabatan yang dicetak"
                @update:model-value="
                    $emit('change', { roleName: String($event) })
                "
            />
            <div class="flex">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Naikkan kolom"
                    :disabled="index === 0"
                    @click="$emit('move', -1)"
                >
                    <ArrowUp />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Turunkan kolom"
                    :disabled="last"
                    @click="$emit('move', 1)"
                >
                    <ArrowDown />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Hapus kolom"
                    @click="$emit('remove')"
                >
                    <Trash2 class="text-destructive" />
                </Button>
            </div>
        </div>
        <SignerModeFields
            :column="column"
            :index="index"
            :first="first"
            :signer-options="signerOptions"
            @change="$emit('change', $event)"
        />
        <InputError :message="error" />
    </div>
</template>
