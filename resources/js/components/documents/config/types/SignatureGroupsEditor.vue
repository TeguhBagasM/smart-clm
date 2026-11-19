<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue';
import Panel from '@/components/documents/Panel.vue';
import SignatureColumnRow from '@/components/documents/config/types/SignatureColumnRow.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { moveItem, removeItem } from '@/lib/arrays';
import type {
    ChoiceOption,
    SignatureColumn,
    SignatureGroup,
    SignerOption,
} from '@/types';

const model = defineModel<SignatureGroup[]>({ required: true });

defineProps<{
    roleOptions: ChoiceOption[];
    signerOptions: SignerOption[];
    errors: Record<string, string>;
}>();

function editGroup(index: number, patch: Partial<SignatureGroup>): void {
    model.value = model.value.map((group, position) =>
        position === index ? { ...group, ...patch } : group,
    );
}

function editColumn(
    group: number,
    column: number,
    patch: Partial<SignatureColumn>,
): void {
    editGroup(group, {
        columns: model.value[group].columns.map((item, position) =>
            position === column ? { ...item, ...patch } : item,
        ),
    });
}
</script>

<template>
    <Panel title="Penandatangan" :count="model.length">
        <p class="mb-3 text-sm text-muted-foreground">
            Kolom tanda tangan dicetak di lembar pengesahan dan ditandatangani
            berurutan dari atas ke bawah. Kolom bisa dikelompokkan, mis.
            "Penyusun:" dan "Mengetahui:".
        </p>
        <div class="flex flex-col gap-4">
            <section
                v-for="(group, groupIndex) in model"
                :key="groupIndex"
                class="flex flex-col gap-3 rounded-lg border p-3"
            >
                <div class="flex items-center gap-2">
                    <Input
                        :model-value="group.heading ?? ''"
                        placeholder="Judul kelompok (opsional), mis. Penyusun:"
                        :aria-label="`Judul kelompok ${groupIndex + 1}`"
                        @update:model-value="
                            editGroup(groupIndex, { heading: String($event) })
                        "
                    />
                    <Button
                        v-if="model.length > 1"
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Hapus kelompok"
                        @click="model = removeItem(model, groupIndex)"
                    >
                        <Trash2 class="text-destructive" />
                    </Button>
                </div>
                <SignatureColumnRow
                    v-for="(column, columnIndex) in group.columns"
                    :key="columnIndex"
                    :column="column"
                    :index="columnIndex"
                    :last="columnIndex === group.columns.length - 1"
                    :role-options="roleOptions"
                    :signer-options="signerOptions"
                    :first="groupIndex === 0 && columnIndex === 0"
                    :error="
                        errors[
                            `signatureGroups.${groupIndex}.columns.${columnIndex}.label`
                        ]
                    "
                    @change="editColumn(groupIndex, columnIndex, $event)"
                    @move="
                        editGroup(groupIndex, {
                            columns: moveItem(
                                group.columns,
                                columnIndex,
                                $event,
                            ),
                        })
                    "
                    @remove="
                        editGroup(groupIndex, {
                            columns: removeItem(group.columns, columnIndex),
                        })
                    "
                />
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="self-start"
                    @click="
                        editGroup(groupIndex, {
                            columns: [
                                ...group.columns,
                                {
                                    label: '',
                                    role: '',
                                    fillMode: 'at_creation',
                                    defaultUserId: null,
                                    allowCreator: false,
                                },
                            ],
                        })
                    "
                >
                    <Plus /> Tambah kolom
                </Button>
            </section>
        </div>
        <Button
            type="button"
            variant="outline"
            size="sm"
            class="mt-3"
            @click="
                model = [
                    ...model,
                    {
                        heading: '',
                        columns: [
                            {
                                label: '',
                                role: '',
                                fillMode: 'at_creation',
                                defaultUserId: null,
                                allowCreator: false,
                            },
                        ],
                    },
                ]
            "
        >
            <Plus /> Tambah kelompok
        </Button>
    </Panel>
</template>
