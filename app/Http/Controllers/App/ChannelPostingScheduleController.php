<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\SocialAccount\CopyPostingSchedule;
use App\Actions\SocialAccount\RegeneratePostingSchedule;
use App\Actions\SocialAccount\UpdatePostingSchedule;
use App\Http\Requests\App\Channel\CopyPostingScheduleRequest;
use App\Http\Requests\App\Channel\GeneratePostingScheduleRequest;
use App\Http\Requests\App\Channel\UpdatePostingScheduleRequest;
use App\Http\Resources\App\ChannelPostingScheduleResource;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ChannelPostingScheduleController extends Controller
{
    public function update(UpdatePostingScheduleRequest $request, SocialAccount $account, UpdatePostingSchedule $update): ChannelPostingScheduleResource
    {
        $this->authorizeChannel($request, $account);

        $update->handle($account, $request->validated());

        return ChannelPostingScheduleResource::make($account);
    }

    public function generate(GeneratePostingScheduleRequest $request, SocialAccount $account, RegeneratePostingSchedule $regenerate): ChannelPostingScheduleResource
    {
        $this->authorizeChannel($request, $account);

        $regenerate->handle($account, $request->validated('goal') === null ? null : (int) $request->validated('goal'));

        return ChannelPostingScheduleResource::make($account);
    }

    public function copy(CopyPostingScheduleRequest $request, SocialAccount $account, CopyPostingSchedule $copy): ChannelPostingScheduleResource
    {
        $this->authorizeChannel($request, $account);

        $source = $request->user()->currentWorkspace->socialAccounts()->findOrFail($request->validated('from'));

        return ChannelPostingScheduleResource::make($copy->handle($account, $source));
    }

    private function authorizeChannel(Request $request, SocialAccount $account): void
    {
        abort_unless($account->workspace_id === $request->user()->current_workspace_id, HttpResponse::HTTP_NOT_FOUND);

        $this->authorize('manageAccounts', $request->user()->currentWorkspace);
    }
}
