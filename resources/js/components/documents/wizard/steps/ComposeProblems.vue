<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

const page = usePage();

/** The answer to "Susun PDF" lists every problem as `compose.0`, `compose.1`, ... */
const problems = computed(() =>
    Object.entries(page.props.errors ?? {})
        .filter(([key]) => key.startsWith('compose.'))
        .map(([, message]) => String(message)),
);
</script>

<template>
    <Alert v-if="problems.length > 0" variant="destructive" role="alert">
        <TriangleAlert />
        <AlertTitle>
            Dokumen belum bisa disusun. Perbaiki {{ problems.length }} hal
            berikut:
        </AlertTitle>
        <AlertDescription>
            <ul class="list-disc space-y-1 pl-5">
                <li v-for="problem in problems" :key="problem">
                    {{ problem }}
                </li>
            </ul>
        </AlertDescription>
    </Alert>
</template>
