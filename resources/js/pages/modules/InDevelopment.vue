<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Construction } from '@lucide/vue';
import { computed } from 'vue';
import { navIcon } from '@/components/navIcons';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

const props = defineProps<{
    module: string;
    page: string;
    icon: string;
}>();

const PageIcon = computed(() => navIcon(props.icon));
</script>

<template>
    <Head :title="`${page} · ${module}`" />

    <div class="flex flex-1 items-center justify-center p-4 md:p-6">
        <div
            class="flex w-full max-w-lg flex-col items-center gap-4 rounded-xl border border-dashed bg-card p-6 text-center md:p-10"
        >
            <div
                class="flex size-14 items-center justify-center rounded-full bg-muted text-muted-foreground"
            >
                <component :is="PageIcon" class="size-7" />
            </div>
            <div class="flex flex-col gap-1">
                <p class="text-sm text-muted-foreground">{{ module }}</p>
                <h1 class="text-xl font-semibold">{{ page }}</h1>
            </div>
            <Badge variant="secondary" class="gap-1">
                <Construction class="size-3.5" /> Sedang dikembangkan
            </Badge>
            <p class="text-sm text-muted-foreground">
                Halaman ini sedang dikembangkan dan akan tersedia setelah modul
                {{ module }} selesai dimigrasi.
            </p>
            <Button variant="outline" class="w-full sm:w-auto" as-child>
                <Link :href="dashboard()">Kembali ke Dashboard</Link>
            </Button>
        </div>
    </div>
</template>
