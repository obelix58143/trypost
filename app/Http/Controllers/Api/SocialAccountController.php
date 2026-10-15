<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Post\Queue\ListFreeQueueSlots;
use App\Actions\SocialAccount\CreatePinterestBoard;
use App\Actions\SocialAccount\ListDiscordChannels;
use App\Actions\SocialAccount\ListPinterestBoards;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\PlatformUnavailableException;
use App\Exceptions\Social\ErrorCategory;
use App\Exceptions\Social\PinterestPublishException;
use App\Exceptions\TokenExpiredException;
use App\Http\Requests\Api\SocialAccount\StorePinterestBoardRequest;
use App\Http\Resources\Api\ChannelFreeSlotsResource;
use App\Http\Resources\Api\ChannelPostingScheduleResource;
use App\Http\Resources\Api\PinterestBoardResource;
use App\Http\Resources\Api\SocialAccountResource;
use App\Http\Resources\Api\TikTokCreatorInfoResource;
use App\Models\SocialAccount;
use App\Services\Social\TikTokCreatorInfo;
use App\Support\Social\PinterestBoardFailure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class SocialAccountController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $accounts = $request->user()->currentWorkspace->socialAccounts()->paginate((int) config('app.pagination.default'));

        return SocialAccountResource::collection($accounts);
    }

    public function boards(Request $request, SocialAccount $account): JsonResponse
    {
        $this->authorize('view', $account);

        if ($account->platform !== Platform::Pinterest) {
            return response()->json(
                ['message' => 'Boards are only available for Pinterest accounts.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        try {
            return response()->json(ListPinterestBoards::execute($account));
        } catch (TokenExpiredException $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                Response::HTTP_UNAUTHORIZED,
            );
        } catch (PinterestPublishException $e) {
            return response()->json(
                ['message' => $e->userMessage],
                $this->statusForPinterestCategory($e->category),
            );
        }
    }

    public function storeBoard(StorePinterestBoardRequest $request, SocialAccount $account): JsonResponse
    {
        try {
            $board = CreatePinterestBoard::execute($account, $request->validated());
        } catch (TokenExpiredException|PinterestPublishException|ConnectionException $e) {
            throw ValidationException::withMessages(['name' => PinterestBoardFailure::message($e)]);
        }

        return (new PinterestBoardResource($board))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function channels(Request $request, SocialAccount $account): JsonResponse
    {
        $this->authorize('view', $account);

        if ($account->platform !== Platform::Discord) {
            return response()->json(
                ['message' => 'Channels are only available for Discord accounts.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        try {
            return response()->json([
                'channels' => ListDiscordChannels::execute($account),
            ]);
        } catch (PlatformUnavailableException $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                Response::HTTP_BAD_GATEWAY,
            );
        }
    }

    public function tiktokCreatorInfo(SocialAccount $account, TikTokCreatorInfo $tikTokCreatorInfo): JsonResponse
    {
        $this->authorize('view', $account);

        if ($account->platform !== Platform::TikTok) {
            return response()->json(
                ['message' => 'Creator info is only available for TikTok accounts.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        try {
            $creatorInfo = $tikTokCreatorInfo->interactive()->fetchOrFail($account);
        } catch (PlatformUnavailableException $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $e->httpStatus ?? Response::HTTP_BAD_GATEWAY,
                $e->retryDelaySeconds === null ? [] : ['Retry-After' => (string) $e->retryDelaySeconds],
            );
        }

        return (new TikTokCreatorInfoResource($creatorInfo))->response();
    }

    public function postingSchedule(SocialAccount $account): ChannelPostingScheduleResource
    {
        $this->authorize('view', $account);

        return new ChannelPostingScheduleResource($account);
    }

    public function freeSlots(SocialAccount $account): ChannelFreeSlotsResource
    {
        $this->authorize('view', $account);

        return new ChannelFreeSlotsResource(ListFreeQueueSlots::handle($account));
    }

    private function statusForPinterestCategory(ErrorCategory $category): int
    {
        return match ($category) {
            ErrorCategory::RateLimit => Response::HTTP_TOO_MANY_REQUESTS,
            ErrorCategory::Permission => Response::HTTP_FORBIDDEN,
            default => Response::HTTP_BAD_GATEWAY,
        };
    }
}
