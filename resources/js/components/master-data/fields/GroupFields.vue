<script setup lang="ts">
import { ref } from 'vue';
import CheckboxGroup from '@/components/master-data/CheckboxGroup.vue';
import FieldRow from '@/components/master-data/FieldRow.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import { Input } from '@/components/ui/input';
import type { MasterFormOptions, MasterInfo, MasterRow } from '@/types';

/** The form of a partner group (name and platforms) or of a partner (name and group). */
const props = defineProps<{
    info: MasterInfo;
    row?: MasterRow;
    options: MasterFormOptions;
    errors: Record<string, string>;
}>();

const group = ref(
    props.row?.partner_group_id ? String(props.row.partner_group_id) : '',
);
</script>

<template>
    <FieldRow
        id="grp-name"
        label="Nama"
        :help="info.help.name"
        :error="errors.name"
        required
    >
        <Input
            id="grp-name"
            name="name"
            :default-value="row?.name"
            class="h-11 md:h-9"
            required
        />
    </FieldRow>
    <template v-if="info.key === 'partner-groups'">
        <CheckboxGroup
            name="platform_ids"
            legend="Platform yang memakai kelompok ini"
            :options="
                (options.platforms ?? []).map((p) => ({
                    value: p.value,
                    label: p.label,
                }))
            "
            :selected="row?.platforms?.map((p) => p.id) ?? []"
            empty-text="Belum ada platform."
        />
        <p class="-mt-3 text-xs text-muted-foreground">
            {{ info.help.platform_ids }}
        </p>
    </template>
    <FieldRow
        v-else
        id="grp-group"
        label="Kelompok"
        :help="info.help.partner_group_id"
        :error="errors.partner_group_id"
        required
    >
        <SearchSelect
            id="grp-group"
            v-model="group"
            name="partner_group_id"
            label="Kelompok"
            placeholder="Pilih kelompok"
            :options="
                (options.groups ?? []).map((g) => ({
                    value: String(g.value),
                    label: g.label,
                }))
            "
        />
    </FieldRow>
</template>
