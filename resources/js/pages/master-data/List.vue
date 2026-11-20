<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowDownUp } from '@lucide/vue';
import { computed, ref } from 'vue';
import MasterDataIndexController from '@/actions/App/Http/Controllers/MasterData/MasterDataIndexController';
import MasterListController from '@/actions/App/Http/Controllers/MasterData/MasterListController';
import MasterRecordStateController from '@/actions/App/Http/Controllers/MasterData/MasterRecordStateController';
import ListPage from '@/components/list/ListPage.vue';
import MasterHeader from '@/components/master-data/MasterHeader.vue';
import MasterRecordSheet from '@/components/master-data/MasterRecordSheet.vue';
import MasterTable from '@/components/master-data/MasterTable.vue';
import ReorderList from '@/components/master-data/ReorderList.vue';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useMasterList } from '@/composables/useMasterList';
import { useReorder } from '@/composables/useReorder';
import type {
    ListMeta,
    MasterCompany,
    MasterFormOptions,
    MasterInfo,
    MasterRow,
    OrderRow,
    Paginated,
    ReorderState,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Daftar pilihan', href: MasterDataIndexController() },
        ],
    },
});

const props = defineProps<{
    items: Paginated<MasterRow>;
    list: ListMeta;
    info: MasterInfo;
    options: MasterFormOptions;
    company: MasterCompany;
    companies: { value: number; label: string }[] | null;
    reorder: ReorderState;
    order_rows?: OrderRow[];
}>();

const ordering = ref(false);
const loadingOrder = ref(false);
const loadFailed = ref(false);
const sorter = useReorder(() => props.info.texts.reorder_failed);
const master = useMasterList(() => props.info);
/** A super admin's company choice is kept on every list visit. */
const params = computed(() => {
    const kept: Record<string, string> = {};

    if (props.companies) {
        kept.tenant_id = String(props.company.id);
    }

    return kept;
});

function startOrdering(): void {
    loadingOrder.value = true;
    loadFailed.value = false;
    const fail = (): void => {
        loadFailed.value = true;
    };

    router.reload({
        only: ['order_rows'],
        onSuccess: () => {
            sorter.start(props.order_rows ?? []);
            ordering.value = true;
        },
        onError: fail,
        onHttpException: fail,
        onNetworkError: fail,
        onFinish: () => (loadingOrder.value = false),
    });
}

function saveOrder(): void {
    sorter.save(
        MasterListController.reorder(props.info.key).url,
        { tenant_id: props.company.id },
        () => (ordering.value = false),
    );
}

function toggle(row: MasterRow): void {
    const route = row.is_active
        ? MasterRecordStateController.deactivate
        : MasterRecordStateController.reactivate;

    void master.setActive(
        row,
        row.is_active,
        () => route({ list: props.info.key, record: row.id }).url,
    );
}
</script>

<template>
    <Head :title="info.label" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <MasterHeader
            :title="info.label"
            :description="info.description"
            :company="company"
            :companies="companies"
            :notice="info.usage_notice"
        >
            <template #action>
                <Button
                    v-if="
                        reorder.available && items.meta.total > 1 && !ordering
                    "
                    variant="outline"
                    class="h-11 w-full md:h-9 md:w-auto"
                    :disabled="loadingOrder"
                    @click="startOrdering"
                >
                    <ArrowDownUp />
                    {{ loadingOrder ? 'Memuat…' : 'Atur urutan' }}
                </Button>
                <MasterRecordSheet
                    v-if="!ordering"
                    :info="info"
                    :options="options"
                    :company-id="company.id"
                />
            </template>
        </MasterHeader>

        <p
            v-if="reorder.hint && !ordering"
            class="text-sm text-muted-foreground"
        >
            {{ reorder.hint }}
        </p>
        <Alert
            v-if="loadFailed"
            variant="destructive"
            class="flex flex-wrap items-center justify-between gap-2"
        >
            <p class="text-sm">
                Urutan belum bisa dimuat. Periksa sambungan internet, lalu coba
                lagi.
            </p>
            <Button
                variant="outline"
                size="sm"
                class="h-11 md:h-8"
                @click="startOrdering"
                >Coba lagi</Button
            >
        </Alert>

        <ReorderList
            v-if="ordering"
            :rows="sorter.order.value"
            :saving="sorter.saving.value"
            :dirty="sorter.dirty.value"
            :error="sorter.error.value"
            @move="sorter.move"
            @save="saveOrder"
            @cancel="ordering = false"
        />
        <div
            v-else-if="loadingOrder"
            class="flex flex-col gap-2"
            aria-busy="true"
        >
            <Skeleton v-for="n in 4" :key="n" class="h-14 w-full rounded-xl" />
        </div>
        <ListPage
            v-else
            :list="list"
            :paginated="items"
            :only="['items', 'list', 'reorder']"
            :params="params"
            :search-placeholder="`Cari ${info.label.toLowerCase()}…`"
            :empty-text="`Belum ada ${info.label.toLowerCase()}. Tambahkan yang pertama dengan tombol di atas.`"
        >
            <MasterTable
                :rows="items.data"
                :list="list"
                :info="info"
                :options="options"
                :company-id="company.id"
                :busy="master.busy.value"
                @toggle="toggle"
            />
        </ListPage>
    </div>
</template>
