<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\SocialAccount\Platform;
use App\Exceptions\PlatformUnavailableException;
use App\Http\Resources\Api\TikTokCreatorInfoResource;
use App\Models\SocialAccount;
use App\Services\Social\TikTokCreatorInfo;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TikTokCreatorInfoController extends Controller
{
    public function show(SocialAccount $account, TikTokCreatorInfo $tikTokCreatorInfo): JsonResponse
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
}
