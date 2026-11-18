<script setup lang="ts">
import { AlertTriangle, Check, CloudOff, RefreshCw } from '@lucide/vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { DraftStatus } from '@/composables/useDraftWizard';

defineProps<{ status: DraftStatus }>();

function reload(): void {
    router.visit(window.location.href, { preserveState: false });
}
</script>

<template>
    <div class="text-sm" role="status" aria-live="polite">
        <span
            v-if="status === 'saved'"
            class="inline-flex items-center gap-1.5 text-muted-foreground"
        >
            <Check class="size-4 text-success" /> Tersimpan otomatis
        </span>
        <span
            v-else-if="status === 'dirty' || status === 'saving'"
            class="inline-flex items-center gap-1.5 text-muted-foreground"
        >
            <Spinner class="size-4" /> Menyimpan...
        </span>
        <span
            v-else-if="status === 'error'"
            class="inline-flex items-center gap-1.5 text-destructive"
        >
            <CloudOff class="size-4" /> Gagal menyimpan. Mencoba lagi saat Anda
            melanjutkan.
        </span>
        <span
            v-else
            class="inline-flex flex-wrap items-center gap-2 text-destructive"
        >
            <AlertTriangle class="size-4" /> Draft diubah di tempat lain.
            <Button size="sm" variant="outline" @click="reload">
                <RefreshCw /> Muat ulang
            </Button>
        </span>
    </div>
</template>
