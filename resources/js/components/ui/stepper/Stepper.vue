<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { IconMinus, IconPlus } from "@tabler/icons-vue"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"

const props = withDefaults(defineProps<{
  min?: number
  max: number
  decreaseLabel: string
  increaseLabel: string
  decreaseTestId?: string
  increaseTestId?: string
  valueTestId?: string
  class?: HTMLAttributes["class"]
}>(), {
  min: 1,
})

const model = defineModel<number>({ required: true })

const step = (delta: number): void => {
  model.value = Math.min(props.max, Math.max(props.min, model.value + delta))
}

const decrease = (): void => {
  step(-1)
}

const increase = (): void => {
  step(1)
}
</script>

<template>
  <div
    data-slot="stepper"
    :class="cn('flex h-8 w-fit items-center overflow-hidden rounded-md border border-border-strong bg-card', props.class)"
  >
    <Button
      type="button"
      variant="ghost"
      size="icon"
      class="rounded-none"
      :disabled="model <= min"
      :aria-label="decreaseLabel"
      :data-testid="decreaseTestId"
      @click="decrease"
    >
      <IconMinus class="size-4" />
    </Button>
    <span
      class="min-w-10 px-1 text-center text-sm text-foreground tabular-nums"
      :data-testid="valueTestId"
    >
      <slot :value="model">{{ model }}</slot>
    </span>
    <Button
      type="button"
      variant="ghost"
      size="icon"
      class="rounded-none"
      :disabled="model >= max"
      :aria-label="increaseLabel"
      :data-testid="increaseTestId"
      @click="increase"
    >
      <IconPlus class="size-4" />
    </Button>
  </div>
</template>
