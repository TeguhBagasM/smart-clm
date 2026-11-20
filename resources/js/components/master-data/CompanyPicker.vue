<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import SearchSelect from '@/components/SearchSelect.vue';

/** Only a super admin has no company of their own, so only they see this. */
const props = defineProps<{
    companies: { value: number; label: string }[];
    selected: number | null;
}>();

const page = usePage();
const options = computed(() =>
    props.companies.map((c) => ({ value: String(c.value), label: c.label })),
);

function choose(value: string | null): void {
    router.get(
        new URL(page.url, 'http://localhost').pathname,
        value ? { tenant_id: value } : {},
    );
}
</script>

<template>
    <div class="flex max-w-sm flex-col gap-1.5">
        <label class="text-sm font-medium" for="company-picker">
            Perusahaan
        </label>
        <SearchSelect
            id="company-picker"
            label="Perusahaan"
            :model-value="selected === null ? null : String(selected)"
            :options="options"
            placeholder="Pilih perusahaan"
            @update:model-value="choose"
        />
    </div>
</template>
