<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Support\Social\PendingConnection;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class TikTokController extends SocialController
{
    protected string $driver = 'tiktok';

    protected SocialPlatform $platform = SocialPlatform::TikTok;

    /**
     * Requested scopes come from config so self-hosters whose TikTok app lacks
     * a product (Display API is no longer offered to new apps, taking
     * user.info.profile/stats and video.list with it) can trim the list via
     * TIKTOK_SCOPES instead of hitting a "scope" error on the consent screen.
     */
    private function scopes(): array
    {
        return (array) config('trypost.platforms.tiktok.scopes');
    }

    public function connect(Request $request): Response
    {
        $this->ensurePlatformEnabled();

        $workspace = $request->user()->currentWorkspace;

        $this->authorize('manageAccounts', $workspace);

        return $this->redirectToProvider($request, $this->driver, $this->scopes(), [
            'disable_auto_auth' => 1,
        ]);
    }

    public function callback(Request $request): RedirectResponse
    {
        $workspace = $this->connectWorkspace($request);

        try {
            $socialUser = Socialite::driver($this->driver)
                ->scopes($this->scopes())
                ->user();

            $scopes = $this->reportedScopes($socialUser->approvedScopes, $this->scopes());

            return $this->refusalForMissingPublishScopes($scopes) ?? $this->offerIdentities($workspace, [
                PendingConnection::identity(
                    $this->platform,
                    (string) $socialUser->getId(),
                    $socialUser->getName() ?? $socialUser->getNickname(),
                    $socialUser->getNickname(),
                    $socialUser->getAvatar(),
                    $this->platform->identityType()->value,
                    [
                        'username' => $socialUser->getNickname(),
                        'display_name' => $socialUser->getName(),
                        'access_token' => $socialUser->token,
                        'refresh_token' => $socialUser->refreshToken,
                        'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
                        'scopes' => $scopes,
                    ],
                ),
            ], $this->reconnectAccount($workspace));
        } catch (Exception $e) {
            Log::error('TikTok OAuth Error', [
                'error' => $e->getMessage(),
            ]);

            return $this->failConnection('error_connecting');
        }
    }
}
