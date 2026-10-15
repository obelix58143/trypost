<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `meta.aspect_ratio` made the Facebook and Instagram publishers crop each
     * image on the server. Images now publish at their own ratio, so the key
     * is dropped; meta merges on update would keep it forever otherwise.
     *
     * An enabled Instagram feed or Facebook post target that will publish
     * without user action (pending, publishing or retrying, on a scheduled,
     * pending approval or publishing post) keeps the key:
     * `posts:bake-aspect-ratio-crops` (release:trypost-2) bakes its crop into
     * the post's media after the legacy posts are split, then drops the key
     * from every row. Drafts and failed targets keep their original image.
     */
    public function up(): void
    {
        DB::table('post_platforms')
            ->whereNotNull('meta->aspect_ratio')
            ->whereNot(fn (Builder $query) => $query
                ->where('enabled', true)
                ->whereIn('platform', ['facebook', 'instagram', 'instagram-facebook'])
                ->whereIn('content_type', ['instagram_feed', 'facebook_post'])
                ->where('meta->aspect_ratio', '!=', 'original')
                ->whereIn('status', ['pending', 'publishing', 'retrying'])
                ->whereIn('post_id', DB::table('posts')->select('id')->whereIn('status', ['scheduled', 'pending_approval', 'publishing'])))
            ->select(['id', 'meta'])
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $meta = json_decode((string) $row->meta, true);

                    if (! is_array($meta)) {
                        continue;
                    }

                    unset($meta['aspect_ratio']);

                    DB::table('post_platforms')
                        ->where('id', $row->id)
                        ->update(['meta' => json_encode($meta)]);
                }
            });
    }
};
