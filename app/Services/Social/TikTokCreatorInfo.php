<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\TikTok\PrivacyLevel;
use App\Exceptions\PlatformUnavailableException;
use App\Models\SocialAccount;
use App\Services\Social\Concerns\HasSocialHttpClient;
use App\Support\Social\NetworkLimitReset;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TikTokCreatorInfo
{
    use HasSocialHttpClient;

    /**
     * Error codes with which TikTok answers that the creator cannot post.
     * creator_info/query reports its refusals with HTTP 200 and error.code
     * set, so the code is read whatever the status.
     */
    private const array CANNOT_POST_CODES = [
        'spam_risk_user_banned_from_posting',
    ];

    /**
     * Error codes with which TikTok answers that a limit was reached (daily
     * post cap, daily active-user quota, rate limit). Not "can't post": a
     * scheduled post retries once the limit lifts.
     */
    private const array LIMIT_CODES = [
        'spam_risk_too_many_posts',
        'reached_active_user_cap',
        'rate_limit_exceeded',
    ];

    private string $baseUrl;

    private string $accessToken;

    public function __construct()
    {
        $this->baseUrl = config('trypost.platforms.tiktok.api');
    }

    /**
     * @return array{
     *     creator_nickname: ?string,
     *     creator_username: ?string,
     *     creator_avatar_url: ?string,
     *     privacy_level_options: array<int, string>,
     *     comment_disabled: bool,
     *     duet_disabled: bool,
     *     stitch_disabled: bool,
     *     max_video_post_duration_sec: ?int,
     * }
     */
    public function fetch(SocialAccount $account): array
    {
        $cacheKey = "tiktok:creator_info:{$account->id}";
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $creatorInfo = $this->fetchFresh($account);
        } catch (PlatformUnavailableException) {
            return $this->emptyPayload();
        }

        if ($creatorInfo === null) {
            return $this->emptyPayload();
        }

        Cache::put($cacheKey, $creatorInfo, now()->addMinutes(5));

        return $creatorInfo;
    }

    /**
     * Like fetch(), but a TikTok that did not answer throws instead of looking
     * like a creator who cannot post: 503 on a timeout, 429 on a limit (daily
     * post cap, active-user quota, rate limit; retryDelaySeconds when TikTok
     * says when it lifts), 502 on any other failure. A creator TikTok bans from
     * posting is an answer and comes back with no privacy options. Failures
     * are not cached.
     *
     * @return array{
     *     creator_nickname: ?string,
     *     creator_username: ?string,
     *     creator_avatar_url: ?string,
     *     privacy_level_options: array<int, string>,
     *     comment_disabled: bool,
     *     duet_disabled: bool,
     *     stitch_disabled: bool,
     *     max_video_post_duration_sec: ?int,
     * }
     *
     * @throws PlatformUnavailableException
     */
    public function fetchOrFail(SocialAccount $account): array
    {
        $cacheKey = "tiktok:creator_info:{$account->id}";
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $creatorInfo = $this->fetchFresh($account);
        } catch (ConnectionException) {
            throw new PlatformUnavailableException(__('posts.form.tiktok.creator_info_unavailable'), Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if ($creatorInfo === null) {
            return $this->emptyPayload();
        }

        Cache::put($cacheKey, $creatorInfo, now()->addMinutes(5));

        return $creatorInfo;
    }

    /**
     * @return array{
     *     creator_nickname: ?string,
     *     creator_username: ?string,
     *     creator_avatar_url: ?string,
     *     privacy_level_options: array<int, string>,
     *     comment_disabled: bool,
     *     duet_disabled: bool,
     *     stitch_disabled: bool,
     *     max_video_post_duration_sec: ?int,
     * }|null
     *
     * @throws PlatformUnavailableException
     */
    private function fetchFresh(SocialAccount $account): ?array
    {
        if ($account->needsProactiveTokenRefresh()) {
            app(ConnectionVerifier::class)->refreshToken($account);
        }

        $this->accessToken = $account->access_token;

        $response = $this->getHttpClient()
            ->withBody('{}', 'application/json; charset=UTF-8')
            ->post("{$this->baseUrl}/post/publish/creator_info/query/");

        $errorCode = data_get($response->json(), 'error.code');
        $refused = $response->failed() || ($errorCode !== null && $errorCode !== 'ok');

        if ($refused) {
            Log::warning('TikTok creator_info query failed', [
                'social_account_id' => $account->id,
                'status' => $response->status(),
                'body' => $this->redactResponseBody($response->body()),
            ]);

            if (in_array($errorCode, self::CANNOT_POST_CODES, true)) {
                return null;
            }

            if (in_array($errorCode, self::LIMIT_CODES, true)) {
                $resetAt = NetworkLimitReset::from($response);

                throw new PlatformUnavailableException(
                    __('posts.form.tiktok.creator_info_limit_reached'),
                    Response::HTTP_TOO_MANY_REQUESTS,
                    ['platform_error_code' => $errorCode],
                    $resetAt === null ? null : (int) ceil(now()->diffInSeconds($resetAt)),
                );
            }

            throw new PlatformUnavailableException(__('posts.form.tiktok.creator_info_unavailable'), Response::HTTP_BAD_GATEWAY);
        }

        $data = data_get($response->json(), 'data', []);

        if (blank($data)) {
            return null;
        }

        return [
            'creator_nickname' => data_get($data, 'creator_nickname'),
            'creator_username' => data_get($data, 'creator_username'),
            'creator_avatar_url' => data_get($data, 'creator_avatar_url'),
            'privacy_level_options' => array_values(array_intersect(
                (array) data_get($data, 'privacy_level_options', []),
                PrivacyLevel::values(),
            )),
            'comment_disabled' => (bool) data_get($data, 'comment_disabled', false),
            'duet_disabled' => (bool) data_get($data, 'duet_disabled', false),
            'stitch_disabled' => (bool) data_get($data, 'stitch_disabled', false),
            'max_video_post_duration_sec' => data_get($data, 'max_video_post_duration_sec'),
        ];
    }

    /**
     * @return array{
     *     creator_nickname: null,
     *     creator_username: null,
     *     creator_avatar_url: null,
     *     privacy_level_options: array<int, string>,
     *     comment_disabled: bool,
     *     duet_disabled: bool,
     *     stitch_disabled: bool,
     *     max_video_post_duration_sec: null,
     * }
     */
    private function emptyPayload(): array
    {
        return [
            'creator_nickname' => null,
            'creator_username' => null,
            'creator_avatar_url' => null,
            'privacy_level_options' => [],
            'comment_disabled' => false,
            'duet_disabled' => false,
            'stitch_disabled' => false,
            'max_video_post_duration_sec' => null,
        ];
    }

    private function getHttpClient(): PendingRequest
    {
        return $this->socialHttp()->asJson()->withToken($this->accessToken);
    }
}
