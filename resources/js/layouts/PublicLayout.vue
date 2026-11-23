<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Toaster } from '@/components/ui/sonner';
</script>

<template>
    <div class="min-h-svh bg-background text-foreground">
        <header class="border-b">
            <div
                class="mx-auto flex max-w-3xl items-center gap-2 px-4 py-3 md:px-6"
            >
                <AppLogoIcon class="size-7 fill-current" />
                <span class="font-semibold">Smart CLM</span>
                <span class="text-sm text-muted-foreground">
                    · Tinjauan dokumen
                </span>
            </div>
        </header>
        <main class="mx-auto max-w-3xl px-4 py-6 md:px-6">
            <slot />
        </main>
        <Toaster />
    </div>
</template>
