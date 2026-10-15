<?php

declare(strict_types=1);

namespace App\Support\Social;

use App\Models\SocialAccount;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\RateLimiter;

/**
 * What the Instagram Audio API lookups share: the track shape every surface
 * returns and the per-workspace allowance of lookups. A lookup that reaches
 * Meta spends one, whatever number of calls it makes; answers served from the
 * cache spend none.
 *
 * @see https://developers.facebook.com/docs/instagram-platform/content-publishing/audio-api/
 */
class InstagramAudio
{
    /** Uncached audio lookups a workspace may make per minute, across web, API and MCP. */
    public const int LOOKUPS_PER_MINUTE = 30;

    public const array TYPES = ['music', 'original_sound'];

    /**
     * Counts one uncached lookup for the account's workspace.
     *
     * @throws ThrottleRequestsException when the workspace already spent its minute.
     */
    public static function spend(SocialAccount $account): void
    {
        $key = "instagram-audio:{$account->workspace_id}";

        if (RateLimiter::hit($key, 60) > self::LOOKUPS_PER_MINUTE) {
            throw new ThrottleRequestsException(
                __('posts.form.instagram.music.too_many_searches'),
                headers: ['Retry-After' => RateLimiter::availableIn($key)],
            );
        }
    }

    /**
     * An audio asset as TryPost returns it. Original sounds carry the creator
     * instead of an artist and their profile picture instead of a cover.
     *
     * @return array{id: string, type: string|null, title: string, artist: string|null, cover_url: string|null, duration_ms: int|null, preview_url: string|null}
     */
    public static function present(mixed $track): array
    {
        $duration = data_get($track, 'duration_in_ms');
        $username = data_get($track, 'ig_username');

        return [
            'id' => (string) data_get($track, 'audio_id', data_get($track, 'id', '')),
            'type' => data_get($track, 'audio_type'),
            'title' => (string) data_get($track, 'title', ''),
            'artist' => data_get($track, 'display_artist') ?? (filled($username) ? (string) $username : null),
            'cover_url' => data_get($track, 'cover_artwork_thumbnail_uri')
                ?? data_get($track, 'cover_artwork_thumbnail_url')
                ?? data_get($track, 'profile_picture_url'),
            'duration_ms' => is_numeric($duration) ? (int) $duration : null,
            'preview_url' => data_get($track, 'download_url'),
        ];
    }
}
