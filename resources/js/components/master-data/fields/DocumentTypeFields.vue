<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import { ref } from 'vue';
import CheckboxGroup from '@/components/master-data/CheckboxGroup.vue';
import FieldRow from '@/components/master-data/FieldRow.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { MasterFormOptions, MasterInfo, MasterRow } from '@/types';

const props = defineProps<{
    info: MasterInfo;
    row?: MasterRow;
    options: MasterFormOptions;
    errors: Record<string, string>;
}>();

const module = ref(
    props.row?.module?.value ?? props.options.modules?.[0]?.value ?? '',
);
const codes = ref(props.row?.extra_codes?.map((code) => ({ ...code })) ?? []);
</script>

<template>
    <FieldRow
        id="dt-name"
        label="Nama"
        :help="info.help.name"
        :error="errors.name"
        required
    >
        <Input
            id="dt-name"
            name="name"
            :default-value="row?.name"
            class="h-11 md:h-9"
            required
        />
    </FieldRow>
    <FieldRow
        id="dt-code"
        label="Kode"
        :help="
            row?.is_system
                ? 'Kode bawaan sistem tidak bisa diubah.'
                : info.help.code
        "
        :error="errors.code"
        required
    >
        <Input
            id="dt-code"
            name="code"
            :default-value="row?.code"
            :disabled="row?.is_system"
            class="h-11 uppercase md:h-9"
            required
        />
    </FieldRow>
    <FieldRow
        id="dt-module"
        label="Dipakai di"
        :help="info.help.module"
        :error="errors.module"
        required
    >
        <SearchSelect
            id="dt-module"
            v-model="module"
            name="module"
            label="Dipakai di"
            :options="
                (options.modules ?? []).map((m) => ({
                    value: m.value,
                    label: m.label,
                }))
            "
        />
    </FieldRow>
    <FieldRow
        id="dt-retention"
        label="Lama simpan (tahun)"
        :help="info.help.retention_years"
        :error="errors.retention_years"
    >
        <Input
            id="dt-retention"
            name="retention_years"
            type="number"
            min="1"
            max="100"
            :default-value="row?.retention_years ?? undefined"
            class="h-11 md:h-9"
        />
    </FieldRow>
    <div class="flex flex-col gap-1.5">
        <CheckboxGroup
            name="category_ids"
            legend="Kategori yang cocok"
            :options="options.categories ?? []"
            :selected="row?.categories?.map((c) => c.id) ?? []"
            empty-text="Belum ada kategori."
        />
        <p class="text-xs text-muted-foreground">{{ info.help.categories }}</p>
    </div>
    <fieldset class="flex flex-col gap-2">
        <legend class="mb-1 text-sm font-medium">Kode tambahan di nomor</legend>
        <p class="text-xs text-muted-foreground">{{ info.help.extra_codes }}</p>
        <div
            v-for="(code, index) in codes"
            :key="index"
            class="flex items-start gap-2"
        >
            <Input
                v-model="code.label"
                :name="`extra_codes[${index}][label]`"
                placeholder="Nama, mis. Divisi"
                :aria-label="`Nama kode tambahan ${index + 1}`"
                class="h-11 md:h-9"
            />
            <Input
                v-model="code.value"
                :name="`extra_codes[${index}][value]`"
                placeholder="Kode, mis. MKT"
                :aria-label="`Isi kode tambahan ${index + 1}`"
                class="h-11 uppercase md:h-9"
            />
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="size-11 shrink-0 md:size-9"
                :aria-label="`Hapus kode tambahan ${index + 1}`"
                @click="codes.splice(index, 1)"
            >
                <X class="size-4" />
            </Button>
        </div>
        <Button
            type="button"
            variant="outline"
            class="h-11 self-start md:h-9"
            :disabled="codes.length >= 10"
            @click="codes.push({ label: '', value: '' })"
        >
            <Plus /> Tambah kode
        </Button>
        <p
            v-for="(message, key) in errors"
            v-show="String(key).startsWith('extra_codes')"
            :key="key"
            class="text-sm text-destructive"
        >
            {{ message }}
        </p>
    </fieldset>
</template>
