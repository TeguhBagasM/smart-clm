<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import TaskQueueController from '@/actions/App/Http/Controllers/Documents/TaskQueueController';
import QueueTable from '@/components/documents/QueueTable.vue';
import TaskTabs from '@/components/documents/TaskTabs.vue';
import Heading from '@/components/Heading.vue';
import ListPage from '@/components/list/ListPage.vue';
import type {
    ListMeta,
    Paginated,
    QueueItem,
    TaskKindInfo,
    TaskKindTab,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Tugas saya', href: TaskQueueController.index() },
        ],
    },
});

const props = defineProps<{
    items: Paginated<QueueItem>;
    list: ListMeta;
    kind: TaskKindInfo;
    kinds: TaskKindTab[];
}>();

const total = computed(() =>
    props.kinds.reduce((sum, kind) => sum + kind.count, 0),
);
</script>

<template>
    <Head title="Tugas saya" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <div class="-mb-4">
            <Heading
                title="Tugas saya"
                :description="
                    total > 0
                        ? `${total} dokumen menunggu tindakan Anda.`
                        : 'Tidak ada dokumen yang menunggu tindakan Anda saat ini.'
                "
            />
        </div>

        <TaskTabs :kinds="kinds" :active="kind.value" />
        <p class="-mt-2 text-sm text-muted-foreground">
            {{ kind.description }}
        </p>

        <ListPage
            :list="list"
            :paginated="items"
            :only="['items', 'list']"
            :params="{ kind: kind.value }"
            search-placeholder="Cari nomor atau judul dokumen..."
            :empty-text="kind.empty"
        >
            <QueueTable :rows="items.data" :list="list" :action="kind.action" />
        </ListPage>
    </div>
</template>
