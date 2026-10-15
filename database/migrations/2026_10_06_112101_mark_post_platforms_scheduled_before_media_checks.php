<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Before TryPost 2.0 nothing checked a target's media at publish time: each
     * publisher sent what the network takes (the first item of a reel, story,
     * Short, pin or Google post, the first four Bluesky or Mastodon items, the
     * first ten Telegram, Discord or LinkedIn ones) at any ratio the network
     * accepts. 2.0 rechecks the media before calling the network. A target
     * that will publish without user action when this runs (pending,
     * publishing or retrying, on a scheduled, pending approval or publishing
     * post) was scheduled under the old rules, so it is marked and
     * `PublishToSocialPlatform` publishes it as before. Drafts and failed
     * targets publish only after the user acts, under the 2.0 rules.
     */
    public function up(): void
    {
        DB::table('post_platforms')
            ->whereIn('status', ['pending', 'publishing', 'retrying'])
            ->whereIn('post_id', DB::table('posts')->select('id')->whereIn('status', ['scheduled', 'pending_approval', 'publishing']))
            ->update(['scheduled_before_media_checks' => true]);
    }
};
