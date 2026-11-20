<script setup lang="ts">
import FieldRow from '@/components/master-data/FieldRow.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { fill } from '@/lib/masterData';
import { vendorFieldValue, vendorInputs } from '@/lib/vendors';
import type { VendorInfo, VendorRow } from '@/types';

/**
 * The vendor form, in the sections the server lists. The account number is
 * write-only: it starts empty, the help says which account is stored, and a
 * checkbox removes it.
 */
const props = defineProps<{
    info: VendorInfo;
    row?: VendorRow;
    errors: Record<string, string>;
}>();

const id = (key: string): string => `vendor-${key.replaceAll('_', '-')}`;

function help(key: string): string {
    const field = props.info.fields[key];

    return key === 'bank_account_number' &&
        props.row?.bank_account_tail &&
        field.keep_help
        ? fill(field.keep_help, { tail: props.row.bank_account_tail })
        : field.help;
}
</script>

<template>
    <fieldset
        v-for="section in info.sections"
        :key="section.key"
        class="flex flex-col gap-4 border-t pt-4 first-of-type:border-t-0 first-of-type:pt-0"
    >
        <legend class="mb-3 text-sm font-semibold">{{ section.title }}</legend>
        <FieldRow
            v-for="key in section.fields"
            :id="id(key)"
            :key="key"
            :label="info.fields[key].label"
            :help="help(key)"
            :error="errors[key]"
            :required="key === 'name'"
        >
            <Textarea
                v-if="key === 'address'"
                :id="id(key)"
                :name="key"
                :default-value="vendorFieldValue(row, key)"
                :aria-describedby="`${id(key)}-help`"
                rows="3"
            />
            <Input
                v-else
                :id="id(key)"
                :name="key"
                :type="vendorInputs[key]?.type ?? 'text'"
                :inputmode="vendorInputs[key]?.inputmode"
                :placeholder="info.fields[key].placeholder"
                :default-value="vendorFieldValue(row, key)"
                :autocomplete="
                    key === 'bank_account_number' ? 'off' : undefined
                "
                :aria-describedby="`${id(key)}-help`"
                :aria-invalid="errors[key] ? true : undefined"
                :required="key === 'name'"
                class="h-11 md:h-9"
            />
            <label
                v-if="
                    key === 'bank_account_number' &&
                    row?.bank_account_tail &&
                    info.fields[key].clear
                "
                class="flex min-h-11 cursor-pointer items-center gap-3 text-sm"
            >
                <Checkbox name="clear_bank_account_number" value="1" />
                {{ info.fields[key].clear }}
            </label>
        </FieldRow>
    </fieldset>
</template>
