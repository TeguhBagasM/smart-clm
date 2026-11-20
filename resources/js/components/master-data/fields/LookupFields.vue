<script setup lang="ts">
import FieldRow from '@/components/master-data/FieldRow.vue';
import { Input } from '@/components/ui/input';
import type { MasterInfo, MasterRow } from '@/types';

/** Name and code of the five simple lists. The code of a default row is fixed. */
defineProps<{
    info: MasterInfo;
    row?: MasterRow;
    errors: Record<string, string>;
}>();
</script>

<template>
    <FieldRow
        id="master-name"
        label="Nama"
        :help="info.help.name"
        :error="errors.name"
        required
    >
        <Input
            id="master-name"
            name="name"
            :default-value="row?.name"
            class="h-11 md:h-9"
            required
        />
    </FieldRow>
    <FieldRow
        v-if="info.columns.some((column) => column.key === 'code')"
        id="master-code"
        label="Kode"
        :help="
            row?.is_system
                ? 'Kode bawaan sistem tidak bisa diubah.'
                : info.help.code
        "
        :error="errors.code"
    >
        <Input
            id="master-code"
            name="code"
            :default-value="row?.code"
            :disabled="row?.is_system"
            class="h-11 uppercase md:h-9"
            :required="info.key === 'currencies'"
        />
    </FieldRow>
</template>
