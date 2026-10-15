<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\SocialAccount\ListDiscordChannels;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\PlatformUnavailableException;
use App\Models\SocialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DiscordController extends Controller
{
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
}
