<script setup lang="ts">
import { useBelowBreakpoint } from '@/composables/useBreakpoint';

const compact = useBelowBreakpoint('md');
</script>

<template>
    <Teleport defer to="#app-header-actions" :disabled="compact">
        <slot />
    </Teleport>
</template>
