<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\SocialAccount\CopyPostingSchedule;
use App\Actions\SocialAccount\RegeneratePostingSchedule;
use App\Actions\SocialAccount\UpdatePostingSchedule;
use App\Http\Requests\Api\Channel\CopyPostingScheduleRequest;
use App\Http\Requests\Api\Channel\GeneratePostingScheduleRequest;
use App\Http\Requests\Api\Channel\UpdatePostingScheduleRequest;
use App\Http\Resources\Api\ChannelPostingScheduleResource;
use App\Models\SocialAccount;

class ChannelPostingScheduleController extends Controller
{
    public function update(UpdatePostingScheduleRequest $request, SocialAccount $account, UpdatePostingSchedule $update): ChannelPostingScheduleResource
    {
        $update->handle($account, $request->validated());

        return new ChannelPostingScheduleResource($account);
    }

    public function generate(GeneratePostingScheduleRequest $request, SocialAccount $account, RegeneratePostingSchedule $regenerate): ChannelPostingScheduleResource
    {
        $regenerate->handle($account, $request->validated('goal') === null ? null : (int) $request->validated('goal'));

        return new ChannelPostingScheduleResource($account);
    }

    public function copy(CopyPostingScheduleRequest $request, SocialAccount $account, CopyPostingSchedule $copy): ChannelPostingScheduleResource
    {
        $source = $request->user()->currentWorkspace->socialAccounts()->findOrFail($request->validated('from'));

        return new ChannelPostingScheduleResource($copy->handle($account, $source));
    }
}
