<?php

declare(strict_types=1);

namespace App\Actions\SocialAccount;

use App\Actions\Post\Queue\ReflowChannelQueue;
use App\Models\SocialAccount;
use App\Support\PostingSchedule;
use Illuminate\Support\Facades\DB;

class UpdatePostingSchedule
{
    /**
     * Saves the schedule and reflows the queue under the channel lock, so a busy
     * queue fails the whole change instead of leaving posts on the old zone.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(SocialAccount $account, array $data): SocialAccount
    {
        return ReflowChannelQueue::withLock([$account->id], fn (): SocialAccount => DB::transaction(function () use ($account, $data): SocialAccount {
            $account->refresh();
            $schedule = data_get($data, 'posting_schedule');
            $previousTimezone = $account->timezone;

            $account->update([
                'timezone' => data_get($data, 'timezone'),
                'posting_goal' => data_get($data, 'posting_goal'),
                'posting_schedule' => $schedule === null ? null : PostingSchedule::fromArray($schedule),
            ]);

            ReflowChannelQueue::handleLocked($account, previousTimezone: $previousTimezone);

            return $account;
        }));
    }
}
