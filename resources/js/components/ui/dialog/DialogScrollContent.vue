<script setup lang="ts">
import type { DialogContentEmits, DialogContentProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { IconX } from "@tabler/icons-vue"
import {
  DialogClose,
  DialogContent,
  DialogOverlay,
  DialogPortal,
  useForwardPropsEmits,
} from "reka-ui"
import { buttonVariants } from "@/components/ui/button"
import { cn } from "@/lib/utils"

defineOptions({
  inheritAttrs: false,
})

const props = defineProps<DialogContentProps & { class?: HTMLAttributes["class"] }>()
const emits = defineEmits<DialogContentEmits>()

const delegatedProps = reactiveOmit(props, "class")

const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <DialogPortal>
    <DialogOverlay
      class="motion-dialog-overlay fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-black/50 dark:bg-black/60"
    >
      <DialogContent
        data-slot="dialog-content"
        :class="
          cn(
            'motion-dialog relative z-50 grid w-full max-w-[calc(100%-2rem)] my-8 gap-4 rounded-2xl bg-background px-6 pt-6 pb-4 shadow-lg duration-200 sm:max-w-lg md:w-full dark:border max-sm:my-0 max-sm:flex max-sm:h-dvh max-sm:max-w-none max-sm:flex-col max-sm:overflow-y-auto max-sm:rounded-none max-sm:shadow-none max-sm:[&>:has([data-slot$=dialog-footer])]:flex max-sm:[&>:has([data-slot$=dialog-footer])]:grow max-sm:[&>:has([data-slot$=dialog-footer])]:flex-col max-sm:[&>[data-slot$=dialog-header]]:sticky max-sm:[&>[data-slot$=dialog-header]]:top-0 max-sm:[&>[data-slot$=dialog-header]]:z-10 max-sm:[&>[data-slot$=dialog-header]]:bg-background max-sm:[&>[data-slot$=dialog-header]]:shadow-[0_-1.5rem_0_1.5rem_var(--background)]',
            props.class,
          )
        "
        v-bind="{ ...$attrs, ...forwarded }"
        @pointer-down-outside="(event) => {
          const originalEvent = event.detail.originalEvent;
          const target = originalEvent.target as HTMLElement;
          if (originalEvent.offsetX > target.clientWidth || originalEvent.offsetY > target.clientHeight) {
            event.preventDefault();
          }
        }"
      >
        <slot />

        <DialogClose
          data-slot="dialog-close"
          data-testid="dialog-close"
          :class="cn(buttonVariants({ variant: 'ghost', size: 'icon' }), 'absolute top-4 end-4 max-sm:fixed max-sm:top-[calc(1rem+env(safe-area-inset-top))] max-sm:end-[calc(1rem+env(safe-area-inset-right))] max-sm:z-20')"
        >
          <IconX class="size-4" />
          <span class="sr-only">{{ $t('common.close') }}</span>
        </DialogClose>
      </DialogContent>
    </DialogOverlay>
  </DialogPortal>
</template>
