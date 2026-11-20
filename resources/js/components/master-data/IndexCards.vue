<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import type { MasterGroup } from '@/types';

/** One section per group, one card per list: what it is for and how many active rows there are. */
defineProps<{ groups: MasterGroup[]; companyId: number | null }>();

const href = (url: string, companyId: number | null): string =>
    companyId ? `${url}?tenant_id=${companyId}` : url;
</script>

<template>
    <div class="flex flex-col gap-8">
        <section
            v-for="group in groups"
            :key="group.key"
            :aria-labelledby="`group-${group.key}`"
            class="flex flex-col gap-3"
        >
            <div>
                <h2 :id="`group-${group.key}`" class="text-lg font-semibold">
                    {{ group.title }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ group.description }}
                </p>
            </div>
            <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <li v-for="item in group.items" :key="item.key">
                    <Link
                        :href="href(item.href, companyId)"
                        class="flex h-full min-h-11 flex-col gap-2 rounded-xl border bg-card p-4 transition-colors hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/70 focus-visible:outline-none"
                    >
                        <span class="flex items-center justify-between gap-2">
                            <span class="font-medium">{{ item.label }}</span>
                            <ChevronRight
                                class="size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </span>
                        <span class="text-sm text-muted-foreground">{{
                            item.description
                        }}</span>
                        <span
                            v-if="item.count !== null"
                            class="mt-auto pt-1 text-sm"
                        >
                            <strong>{{ item.count }}</strong> tampil di pilihan
                        </span>
                    </Link>
                </li>
            </ul>
        </section>
    </div>
</template>
