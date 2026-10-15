<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Post\Queue\ListTakenSlots;
use App\Actions\SocialAccount\ListPinterestBoards;
use App\Enums\SocialAccount\Platform;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Post\ListComposerTakenSlotsRequest;
use App\Http\Requests\App\Post\ShowComposerAccountDataRequest;
use App\Http\Resources\App\ComposerAccountDataResource;
use App\Http\Resources\App\ComposerTakenSlotsResource;
use App\Models\SocialAccount;
use App\Services\Social\TikTokCreatorInfo;
use Symfony\Component\HttpFoundation\Response;

class ComposerAccountDataController extends Controller
{
    public function show(ShowComposerAccountDataRequest $request, SocialAccount $account, TikTokCreatorInfo $tikTokCreatorInfo): ComposerAccountDataResource
    {
        return new ComposerAccountDataResource(match ($account->platform) {
            Platform::Pinterest => [
                'pinterestBoards' => rescue(fn (): array => ListPinterestBoards::cached($account), ['boards' => [], 'truncated' => false], report: false),
            ],
            Platform::TikTok => [
                'tiktokCreatorInfo' => rescue(fn (): array => $tikTokCreatorInfo->interactive()->fetch($account), null, report: false),
            ],
            default => abort(Response::HTTP_NOT_FOUND),
        });
    }

    public function takenSlots(ListComposerTakenSlotsRequest $request, SocialAccount $account): ComposerTakenSlotsResource
    {
        $taken = ListTakenSlots::handle([$account->id], $request->date('from')->max(now()), $request->date('to'));

        return new ComposerTakenSlotsResource($taken[$account->id]);
    }
}
