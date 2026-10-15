<?php

declare(strict_types=1);

namespace App\Actions\Analytics;

use App\Dto\Analytics\DateRange;
use App\Dto\Analytics\PublicationFilter;
use App\Enums\PostPlatform\ContentType;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\Analytics\ChannelMetrics;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class BuildChannelInsights
{
    public function __construct(
        private readonly ResolveAnalyticsRangePreset $presets,
        private readonly ResolveAnalyticsAccountKey $accountKeys,
        private readonly BuildWorkspaceAnalyticsReport $analytics,
        private readonly ListAvailableChannelMetrics $availableMetrics,
        private readonly ListChannelPublicationPerformance $performance,
        private readonly BuildChannelMetricSeries $series,
        private readonly BuildFollowerGrowthRateSeries $growthRate,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array{filters: array<string, mixed>, available_metrics: list<string>, summary: array<string, mixed>, metric_series: array<string, mixed>}
     */
    public function handle(SocialAccount $channel, User $user, array $validated): array
    {
        $context = $this->context($channel, $user, $validated);
        ['bounds' => $bounds, 'range' => $current] = data_get($context, 'resolved');

        return [
            'filters' => $this->filters($context, $validated),
            'available_metrics' => data_get($context, 'metrics'),
            'summary' => $this->analytics->execute($channel->workspace, $current, $bounds, $channel, data_get($context, 'key'), $user->week_starts_on, data_get($context, 'filter')),
            'metric_series' => [
                ...$this->series->handle($channel, data_get($context, 'key'), $current, $user->week_starts_on, data_get($context, 'filter'), data_get($context, 'availability')),
                'growth' => data_get($context, 'availability.net_followers')
                    ? $this->growthRate->handle($channel->workspace_id, data_get($context, 'key'), now($user->timezone))
                    : null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{filters: array<string, mixed>, publications: LengthAwarePaginator}
     */
    public function publications(SocialAccount $channel, User $user, array $validated, ?int $page = null): array
    {
        $context = $this->context($channel, $user, $validated);
        $current = data_get($context, 'resolved.range');
        $range = data_get($validated, 'period', 'current') === 'previous' ? $current->previous() : $current;
        $filters = $this->filters($context, $validated);

        return [
            'filters' => $filters,
            'publications' => $this->performance->handle($channel, $range, data_get($filters, 'sort'), data_get($context, 'key'), data_get($context, 'filter'), $page),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{range: string, resolved: array{bounds: mixed, range: DateRange}, key: string, filter: PublicationFilter, availability: array<string, bool>, metrics: list<string>}
     */
    private function context(SocialAccount $channel, User $user, array $validated): array
    {
        ['range' => $range, 'selection' => $selection, 'clamped' => $clamped] = $this->presets->selection(Arr::only($validated, ['range', 'start', 'end']), $user->timezone);
        $key = $this->accountKeys->for($channel);
        $availability = $this->availableMetrics->availability($channel, $key);

        return [
            'range' => $range,
            'resolved' => $this->analytics->resolveRange($channel->workspace, $selection, [$key], $clamped),
            'key' => $key,
            'filter' => PublicationFilter::fromValidated($channel->platform, $validated),
            'availability' => $availability,
            'metrics' => $this->availableMetrics->handle($channel, $key, $availability),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $validated
     * @return array{range: string, start: string, end: string, period: string, sort: string, labels: list<string>, untagged: bool, types: list<string>}
     */
    private function filters(array $context, array $validated): array
    {
        $current = data_get($context, 'resolved.range');
        $filter = data_get($context, 'filter');

        return [
            'range' => data_get($context, 'range'),
            'start' => $current->start->toDateString(),
            'end' => $current->end->toDateString(),
            'period' => data_get($validated, 'period', 'current'),
            'sort' => ChannelMetrics::sortFor(data_get($validated, 'sort'), data_get($context, 'metrics')),
            'labels' => $filter->labelIds,
            'untagged' => $filter->untagged,
            'types' => array_map(fn (ContentType $type): string => $type->value, $filter->contentTypes),
        ];
    }
}
