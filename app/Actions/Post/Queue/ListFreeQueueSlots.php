<?php

declare(strict_types=1);

namespace App\Actions\Post\Queue;

use App\Actions\Post\BuildPublishPageProps;
use App\Models\Post;
use App\Models\SocialAccount;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class ListFreeQueueSlots
{
    public const LIMIT = 30;

    /**
     * The next free queue slots of the channel as UTC instants, by the same rule as
     * ReflowChannelQueue::isFreeSlot(): a slot more than a minute out that no scheduled
     * post and no queue request pending approval holds.
     *
     * @return list<CarbonImmutable>
     */
    public static function handle(SocialAccount $channel): array
    {
        $after = now()->addMinute();

        $taken = Post::query()
            ->occupyingSlotsOn($channel->id, $after)
            ->pluck('scheduled_at')
            ->mapWithKeys(fn (CarbonInterface $at): array => [$at->getTimestamp() => true])
            ->all();

        $horizon = now()->addDays(BuildPublishPageProps::MAX_QUEUE_DAYS);
        $free = [];

        foreach ($channel->posting_schedule?->slotsAfter($after, $channel->timezone) ?? [] as $slot) {
            if ($slot->greaterThan($horizon)) {
                break;
            }

            if (isset($taken[$slot->getTimestamp()])) {
                continue;
            }

            $free[] = $slot;

            if (count($free) === self::LIMIT) {
                break;
            }
        }

        return $free;
    }
}
