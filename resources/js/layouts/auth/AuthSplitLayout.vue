<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { FileCheck2, ShieldCheck, Workflow } from '@lucide/vue';
import BrandMark from '@/components/BrandMark.vue';
import { home } from '@/routes';

const name = usePage().props.name;

defineProps<{
    title?: string;
    description?: string;
}>();

const highlights = [
    { icon: FileCheck2, text: 'Kontrak dan dokumen terkontrol di satu tempat' },
    { icon: Workflow, text: 'Alur review dan persetujuan yang tercatat' },
    { icon: ShieldCheck, text: 'Akses sesuai peran di setiap perusahaan' },
];
</script>

<template>
    <div class="grid min-h-dvh lg:grid-cols-[1fr_1.1fr]">
        <aside
            class="hidden flex-col justify-between bg-primary p-12 text-primary-foreground lg:flex"
        >
            <Link
                :href="home()"
                class="flex items-center gap-3 text-lg font-semibold"
            >
                <BrandMark size="lg" tone="on-primary" />
                {{ name }}
            </Link>

            <div class="max-w-md space-y-8">
                <span class="block h-1 w-12 rounded-full bg-highlight" />
                <p class="text-3xl leading-tight font-semibold">
                    Contract Lifecycle Management untuk tim legal dan bisnis.
                </p>
                <ul class="space-y-4">
                    <li
                        v-for="item in highlights"
                        :key="item.text"
                        class="flex items-center gap-3 text-sm text-primary-foreground/85"
                    >
                        <component :is="item.icon" class="size-5 shrink-0" />
                        {{ item.text }}
                    </li>
                </ul>
            </div>

            <p class="text-xs text-primary-foreground/90">
                Aplikasi internal. Akun dibuat oleh administrator.
            </p>
        </aside>

        <main class="flex items-center justify-center px-6 py-12 sm:px-12">
            <div class="flex w-full max-w-sm flex-col gap-8">
                <Link
                    :href="home()"
                    class="flex items-center gap-2 font-semibold lg:hidden"
                >
                    <BrandMark size="md" tone="primary" />
                    {{ name }}
                </Link>

                <div class="space-y-2">
                    <h1
                        v-if="title"
                        class="text-2xl font-semibold tracking-tight"
                    >
                        {{ title }}
                    </h1>
                    <p v-if="description" class="text-sm text-muted-foreground">
                        {{ description }}
                    </p>
                </div>

                <slot />
            </div>
        </main>
    </div>
</template>
