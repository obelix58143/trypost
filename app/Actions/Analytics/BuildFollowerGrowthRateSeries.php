<?php

declare(strict_types=1);

namespace App\Actions\Analytics;

use App\Models\AnalyticsAccountDailySnapshot;
use Carbon\CarbonImmutable;

/**
 * The channel's monthly follower growth rate over the last twelve months, the current month included: the
 * change between the last follower count of a month and the last count of the month before, as a percentage
 * of that earlier count. A month without a follower count, or after one, has no rate. It reads followers
 * account-wide, so the date range, labels and post types of the page do not apply.
 */
class BuildFollowerGrowthRateSeries
{
    public const int MONTHS = 12;

    /**
     * @return array{range: array{start: string, end: string}, months: list<array{month: string, start: string, end: string, followers: int|null, rate: float|null}>, latest: float|null, previous: float|null}
     */
    public function handle(string $workspaceId, string $accountKey, CarbonImmutable $today): array
    {
        $first = $today->startOfMonth()->subMonths(self::MONTHS - 1);
        $closing = $this->closingFollowers($workspaceId, $accountKey, $first->subMonth(), $today);
        $months = [];
        $before = data_get($closing, $first->subMonth()->format('Y-m'));

        for ($month = $first; $month->lessThanOrEqualTo($today); $month = $month->addMonth()) {
            $followers = data_get($closing, $month->format('Y-m'));
            $months[] = [
                'month' => $month->format('Y-m'),
                'start' => $month->toDateString(),
                'end' => $month->endOfMonth()->min($today)->toDateString(),
                'followers' => $followers,
                'rate' => $followers === null || $before === null || $before === 0
                    ? null
                    : round(($followers - $before) / $before * 100, 2),
            ];
            $before = $followers;
        }

        $rates = array_values(array_filter(array_column($months, 'rate'), fn (?float $rate): bool => $rate !== null));

        return [
            'range' => ['start' => $first->toDateString(), 'end' => $today->toDateString()],
            'months' => $months,
            'latest' => $rates === [] ? null : $rates[array_key_last($rates)],
            'previous' => count($rates) < 2 ? null : $rates[count($rates) - 2],
        ];
    }

    /**
     * The last follower count of each month, keyed by "Y-m".
     *
     * @return array<string, int>
     */
    private function closingFollowers(string $workspaceId, string $accountKey, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $closing = [];
        $snapshots = AnalyticsAccountDailySnapshot::query()
            ->where('workspace_id', $workspaceId)
            ->where('social_account_key', $accountKey)
            ->whereNotNull('followers_count')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->get(['date', 'followers_count']);

        foreach ($snapshots as $snapshot) {
            $closing[$snapshot->date->format('Y-m')] = (int) $snapshot->followers_count;
        }

        return $closing;
    }
}
