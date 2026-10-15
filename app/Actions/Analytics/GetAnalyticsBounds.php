<?php

declare(strict_types=1);

namespace App\Actions\Analytics;

use App\Enums\SocialAccount\Platform;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class GetAnalyticsBounds
{
    /**
     * Account snapshots keep the network's calendar date; publications are dated in the viewer's zone.
     *
     * @param  list<string>|null  $accountKeys  Analytics account keys to scope to; null means every account.
     * @return array{min: ?string, max: ?string}
     */
    public function execute(Workspace $workspace, ?array $accountKeys = null, string $timezone = 'UTC'): array
    {
        $accounts = DB::table('analytics_account_daily_snapshots')
            ->where('workspace_id', $workspace->id)
            ->whereIn('platform', Platform::analyticsValues())
            ->when($accountKeys !== null, fn (Builder $query): Builder => $query->whereIn('social_account_key', $accountKeys))
            ->selectRaw('MIN(date) as earliest, MAX(date) as latest')
            ->first();
        $publications = DB::table('analytics_publications')
            ->where('workspace_id', $workspace->id)
            ->whereIn('platform', Platform::analyticsValues())
            ->when($accountKeys !== null, fn (Builder $query): Builder => $query->whereIn('social_account_key', $accountKeys))
            ->selectRaw('MIN(provider_published_at) as earliest, MAX(provider_published_at) as latest')
            ->first();
        $minimum = array_filter([$this->day($accounts?->earliest, 'UTC'), $this->day($publications?->earliest, $timezone)]);
        $maximum = array_filter([$this->day($accounts?->latest, 'UTC'), $this->day($publications?->latest, $timezone)]);

        return [
            'min' => $minimum ? min($minimum) : null,
            'max' => $maximum ? max($maximum) : null,
        ];
    }

    private function day(?string $value, string $timezone): ?string
    {
        return $value === null ? null : CarbonImmutable::parse($value, 'UTC')->setTimezone($timezone)->toDateString();
    }
}
