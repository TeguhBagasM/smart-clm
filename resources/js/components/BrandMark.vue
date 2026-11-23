<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

/**
 * The company's logo, or the default icon until the company has uploaded one. `tone` says what sits
 * behind the default icon: the sidebar's colour, the sign-in panel's blue, the primary colour, or
 * nothing. A company logo always sits on a plain card so its own colours stay readable.
 */
const props = withDefaults(
    defineProps<{
        size?: 'sm' | 'md' | 'lg';
        tone?: 'sidebar' | 'on-primary' | 'primary' | 'plain';
    }>(),
    { size: 'md', tone: 'plain' },
);

const logoUrl = computed(() => usePage().props.branding?.logo_url ?? null);

const box = { sm: 'size-8', md: 'size-9', lg: 'size-10' } as const;
const icon = { sm: 'size-5', md: 'size-5', lg: 'size-6' } as const;
const plainIcon = { sm: 'size-7', md: 'size-9', lg: 'size-9' } as const;
const chip = {
    sidebar: 'rounded-md bg-sidebar-primary text-sidebar-primary-foreground',
    'on-primary': 'rounded-xl bg-primary-foreground/15',
    primary: 'rounded-lg bg-primary text-primary-foreground',
} as const;
</script>

<template>
    <span
        v-if="logoUrl"
        :class="[
            'flex shrink-0 items-center justify-center overflow-hidden rounded-md border bg-card p-1',
            box[props.size],
        ]"
    >
        <img :src="logoUrl" alt="" class="size-full object-contain" />
    </span>
    <span
        v-else-if="props.tone !== 'plain'"
        :class="[
            'flex shrink-0 items-center justify-center',
            box[props.size],
            chip[props.tone],
        ]"
    >
        <AppLogoIcon :class="icon[props.size]" />
    </span>
    <AppLogoIcon
        v-else
        :class="[
            'shrink-0 text-foreground',
            plainIcon[props.size],
        ]"
    />
</template>
