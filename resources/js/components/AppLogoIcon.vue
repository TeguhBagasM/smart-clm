<script setup lang="ts">
import { Building2 } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

/** The default icon, shown until a company uploads its own logo. */
defineProps<{ className?: HTMLAttributes['class'] }>();
</script>

<template>
    <Building2 :class="className" v-bind="$attrs" aria-hidden="true" />
</template>
