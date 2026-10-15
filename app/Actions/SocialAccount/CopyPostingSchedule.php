<?php

declare(strict_types=1);

namespace App\Actions\SocialAccount;

use App\Actions\Post\Queue\ReflowChannelQueue;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CopyPostingSchedule
{
    public function handle(SocialAccount $account, SocialAccount $source): SocialAccount
    {
        if ($source->posting_schedule === null) {
            throw ValidationException::withMessages(['from' => trans('validation.exists', ['attribute' => 'from'])]);
        }

        return ReflowChannelQueue::withLock([$account->id], fn (): SocialAccount => DB::transaction(function () use ($account, $source): SocialAccount {
            $account->update(['posting_schedule' => $source->posting_schedule]);

            ReflowChannelQueue::handleLocked($account);

            return $account;
        }));
    }
}
