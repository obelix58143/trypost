<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What TikTok allows this creator to publish right now, as the composer reads
 * it. An empty privacy list means TikTok answered that the creator cannot post.
 */
class TikTokCreatorInfoResource extends JsonResource
{
    /**
     * @return array{can_post: bool, privacy_level_options: array<int, string>, comment_disabled: bool, duet_disabled: bool, stitch_disabled: bool, max_video_post_duration_sec: int|null, creator_nickname: string|null, creator_username: string|null, creator_avatar_url: string|null}
     */
    public function toArray(Request $request): array
    {
        $privacyLevels = (array) data_get($this->resource, 'privacy_level_options', []);

        return [
            'can_post' => $privacyLevels !== [],
            'privacy_level_options' => $privacyLevels,
            'comment_disabled' => (bool) data_get($this->resource, 'comment_disabled', false),
            'duet_disabled' => (bool) data_get($this->resource, 'duet_disabled', false),
            'stitch_disabled' => (bool) data_get($this->resource, 'stitch_disabled', false),
            'max_video_post_duration_sec' => data_get($this->resource, 'max_video_post_duration_sec'),
            'creator_nickname' => data_get($this->resource, 'creator_nickname'),
            'creator_username' => data_get($this->resource, 'creator_username'),
            'creator_avatar_url' => data_get($this->resource, 'creator_avatar_url'),
        ];
    }
}
