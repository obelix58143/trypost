<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <div
    data-slot="alert-dialog-footer"
    :class="
      cn(
        '-mx-4 -mb-2 max-sm:sticky max-sm:-bottom-2 max-sm:z-10 max-sm:mt-auto max-sm:shadow-[0_0_0_0.5rem_var(--background)] flex flex-row flex-wrap items-center gap-2 rounded-xl bg-muted p-3 sm:justify-end [&>:is(button,[data-slot=button]):last-child]:min-w-32 [&>:is(button,[data-slot=button]):last-child]:flex-1 sm:[&>:is(button,[data-slot=button]):last-child]:min-w-0 sm:[&>:is(button,[data-slot=button]):last-child]:flex-none max-sm:[&>[data-variant=ghost]]:bg-background [&_[data-variant=ghost]:hover]:bg-secondary',
        props.class,
      )
    "
  >
    <slot />
  </div>
</template>
