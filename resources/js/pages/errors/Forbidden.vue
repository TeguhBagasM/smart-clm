<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ShieldAlert } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

/** A friendly 403: why, and where to go. Texts come from the server. */
defineProps<{ title: string; message: string }>();
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-1 items-center justify-center p-4 md:p-6">
        <div
            class="flex w-full max-w-lg flex-col items-center gap-4 rounded-xl border border-dashed bg-card p-6 text-center md:p-10"
        >
            <div
                class="flex size-14 items-center justify-center rounded-full bg-muted text-muted-foreground"
            >
                <ShieldAlert class="size-7" aria-hidden="true" />
            </div>
            <h1 class="text-xl font-semibold">{{ title }}</h1>
            <p class="text-muted-foreground">{{ message }}</p>
            <Button as-child class="h-11 w-full sm:h-9 sm:w-auto">
                <Link :href="dashboard()">Kembali ke beranda</Link>
            </Button>
        </div>
    </div>
</template>
