<script setup lang="ts">
import { Info } from '@lucide/vue';
</script>

<template>
    <p
        class="flex items-start gap-2 rounded-lg border bg-muted/50 px-3 py-2 text-sm text-muted-foreground"
        role="note"
    >
        <Info class="mt-0.5 size-4 shrink-0" />
        <span>
            <strong class="font-medium text-foreground">
                Dihasilkan oleh AI, verifikasi sebelum digunakan.
            </strong>
            Penandaan merah dan hijau dihitung langsung dari teks; analisis dan
            tinjauan ditulis AI dan dapat keliru.
        </span>
    </p>
</template>
