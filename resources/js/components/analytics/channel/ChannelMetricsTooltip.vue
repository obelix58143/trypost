<script setup lang="ts">
import { computed } from 'vue';

import { activeLocale } from '@/language';
import {
    isPercentMetric,
    type ChartMode,
    type SeriesSpec,
} from '@/lib/channelMetrics';
import { formatPercent } from '@/lib/utils';
import type { MetricSeriesPoint, SeriesMetric } from '@/types/analytics';

const props = withDefaults(
    defineProps<{
        payload?: Record<string, unknown>;
        config?: Record<string, unknown>;
        x?: number | Date;
        specs: SeriesSpec[];
        labels: Partial<Record<SeriesMetric, string>>;
        mode: ChartMode;
        dateLabel: (point: MetricSeriesPoint | undefined) => string;
    }>(),
    { payload: () => ({}) },
);

const periods = computed(() => {
    const current = props.payload.current as MetricSeriesPoint | undefined;
    const previous = props.payload.previous as MetricSeriesPoint | undefined;
    const shown: { key: string; point: MetricSeriesPoint | undefined; dashed: boolean }[] = [];

    if (props.mode !== 'previous') {
        shown.push({ key: 'current', point: current, dashed: false });
    }

    if (props.mode !== 'current') {
        shown.push({ key: 'previous', point: previous, dashed: true });
    }

    return shown;
});

const display = (
    value: number | null | undefined,
    metric: SeriesMetric,
): string => {
    if (value === null || value === undefined) {
        return '—';
    }

    return isPercentMetric(metric)
        ? formatPercent(value)
        : value.toLocaleString(activeLocale.value);
};
</script>

<template>
    <div
        class="min-w-44 rounded-lg border border-border-strong bg-popover px-4 py-3 text-xs text-muted-foreground shadow-md"
        data-testid="insights-metrics-tooltip"
    >
        <div
            v-for="period in periods"
            :key="period.key"
            class="grid gap-1 not-first:mt-3"
            :data-testid="`insights-metrics-tooltip-${period.key}`"
        >
            <p>{{ dateLabel(period.point) }}</p>
            <div
                v-for="spec in specs"
                :key="spec.metric"
                class="flex items-center justify-between gap-4"
            >
                <span class="inline-flex min-w-0 items-center gap-1.5">
                    <span
                        class="size-2.5 shrink-0 rounded-[3px]"
                        :style="
                            period.dashed
                                ? { border: `1.5px dashed ${spec.color}` }
                                : { backgroundColor: spec.color }
                        "
                    />
                    <span class="truncate">{{ labels[spec.metric] }}</span>
                </span>
                <span class="font-semibold text-foreground tabular-nums">{{
                    display(period.point?.values[spec.metric], spec.metric)
                }}</span>
            </div>
        </div>
    </div>
</template>
